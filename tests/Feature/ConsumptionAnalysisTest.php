<?php

namespace Tests\Feature;

use App\Domain\Identity\Services\UserSettingsService;
use App\Domain\Shared\Services\LlmService;
use App\Models\Category;
use App\Models\ConsumableGroup;
use App\Models\ConsumptionAnalysis;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConsumptionAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-18 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_ai_gets_exact_figures_against_the_previous_period_and_the_analysis_is_kept(): void
    {
        $user = User::factory()->create(['default_currency' => 'EUR']);
        $milk = ConsumableGroup::factory()->for($user)->create(['name' => 'Milk', 'unit' => 'l']);
        $litre = Product::factory()->for($user)->create(['unit' => 'l', 'size' => '1', 'consumable_group_id' => $milk->id]);
        $household = Category::factory()->create();
        app(UserSettingsService::class)->updateExcludedConsumptionCategoryIds($user, [$household->id]);

        $november = Receipt::factory()->for($user)->create(['receipt_date' => '2026-11-10']);
        ReceiptItem::factory()->for($november)->create(['quantity' => 4, 'unit_price' => 1.25, 'product_id' => $litre->id]);
        ReceiptItem::factory()->for($november)->create(['name' => 'Detergent', 'category_id' => $household->id]);
        $october = Receipt::factory()->for($user)->create(['receipt_date' => '2026-10-25']);
        ReceiptItem::factory()->for($october)->create(['quantity' => 2, 'unit_price' => 1, 'product_id' => $litre->id]);

        $this->mock(LlmService::class, function ($mock): void {
            $mock->shouldReceive('analyseConsumption')
                ->once()
                ->withArgs(fn (array $payload): bool => $payload['previous_period'] === ['start' => '2026-10-19', 'end' => '2026-11-01']
                    && $payload['totals'] === ['spend' => 5.0, 'previous_spend' => 2.0]
                    && count($payload['items']) === 1
                    && $payload['items'][0]['name'] === 'Milk'
                    && $payload['items'][0]['amount'] === 4.0
                    && $payload['items'][0]['price_per_unit'] === 1.25
                    && $payload['items'][0]['previous']['price_per_unit'] === 1.0)
                ->andReturn(['success' => true, 'data' => [
                    'summary' => 'You drank twice as much milk.',
                    'findings' => [['title' => 'Milk doubled', 'detail' => '4 l instead of 2 l.'], ['detail' => 'no title']],
                    'tips' => ['Buy the 1.5 l pack.', ''],
                ]]);
        });

        $this->actingAs($user)
            ->postJson(route('insights.analyses.store'), ['start_date' => '2026-11-02', 'end_date' => '2026-11-15'])
            ->assertCreated()
            ->assertJsonPath('analysis.summary', 'You drank twice as much milk.')
            ->assertJsonCount(1, 'analysis.findings')
            ->assertJsonPath('analysis.tips', ['Buy the 1.5 l pack.']);

        $this->actingAs($user)->getJson(route('insights.analyses.index'))
            ->assertJsonCount(1, 'analyses');
        $this->actingAs(User::factory()->create())->getJson(route('insights.analyses.index'))
            ->assertJsonCount(0, 'analyses');
    }

    public function test_a_preset_period_is_analysed_against_its_comparison_and_labelled(): void
    {
        $user = User::factory()->create();
        ReceiptItem::factory()->for(Receipt::factory()->for($user)->create(['receipt_date' => '2026-11-05']))->create();

        $this->mock(LlmService::class, function ($mock): void {
            $mock->shouldReceive('analyseConsumption')
                ->once()
                ->withArgs(fn (array $payload): bool => $payload['label'] === 'This month'
                    && $payload['period'] === ['start' => '2026-11-01', 'end' => '2026-11-18']
                    && $payload['previous_period'] === ['start' => '2026-10-01', 'end' => '2026-10-18'])
                ->andReturn(['success' => true, 'data' => ['summary' => 'A quiet month.', 'findings' => [], 'tips' => []]]);
        });

        $this->actingAs($user)->postJson(route('insights.analyses.store'), ['period' => 'this_month'])
            ->assertCreated()
            ->assertJsonPath('analysis.period_label', 'This month');

        $this->actingAs($user)->getJson(route('insights.analyses.index'))
            ->assertJsonPath('analyses.0.period_label', 'This month')
            ->assertJsonPath('default_period', 'last_3_months')
            ->assertJsonPath('periods.0', ['value' => 'this_month', 'label' => 'This month']);

        $this->actingAs($user)->postJson(route('insights.analyses.store'), ['period' => 'forever'])
            ->assertJsonValidationErrors('period');
        $this->actingAs($user)->postJson(route('insights.analyses.store'), ['period' => 'last_month', 'start_date' => '2026-11-01', 'end_date' => '2026-11-02'])
            ->assertJsonValidationErrors('start_date');
    }

    public function test_an_empty_period_or_a_failed_ai_call_saves_nothing(): void
    {
        $user = User::factory()->create();

        $this->mock(LlmService::class, function ($mock): void {
            $mock->shouldReceive('analyseConsumption')->once()->andReturn(['success' => false, 'data' => null, 'error' => 'timeout']);
        });

        $this->actingAs($user)->postJson(route('insights.analyses.store'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Nothing was consumed in this period, so there is nothing to analyse.');

        ReceiptItem::factory()->for(Receipt::factory()->for($user)->create(['receipt_date' => '2026-11-01']))->create();

        $this->actingAs($user)->postJson(route('insights.analyses.store'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The analysis could not be written right now. Please try again later.');

        $this->actingAs($user)->postJson(route('insights.analyses.store'), ['start_date' => '2026-11-01', 'end_date' => '2026-12-01'])
            ->assertJsonValidationErrors('end_date');

        $this->assertSame(0, ConsumptionAnalysis::count());
    }
}
