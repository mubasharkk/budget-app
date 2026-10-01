<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_no_optional_sections_by_default(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('sections', [])
                ->has('availableSections', 4));
    }

    public function test_user_can_choose_sections_and_they_are_stored_under_settings_dashboard(): void
    {
        $user = User::factory()->create(['settings' => ['other' => ['kept' => true]]]);

        $this->actingAs($user)
            ->put(route('dashboard.settings.update'), [
                'sections' => ['most_bought_items', 'expense_overview'],
            ])
            ->assertRedirect(route('dashboard'));

        $settings = $user->fresh()->settings;
        $this->assertSame(['expense_overview', 'most_bought_items'], $settings['dashboard']['sections']);
        $this->assertTrue($settings['other']['kept']);

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('sections', ['expense_overview', 'most_bought_items']));
    }

    public function test_user_can_remove_every_optional_section(): void
    {
        $user = User::factory()->create(['settings' => ['dashboard' => ['sections' => ['items_consumed']]]]);

        $this->actingAs($user)
            ->putJson(route('dashboard.settings.update'), ['sections' => []])
            ->assertRedirect(route('dashboard'));

        $this->assertSame([], $user->fresh()->settings['dashboard']['sections']);
    }

    public function test_unknown_section_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('dashboard.settings.update'), ['sections' => ['at_a_glance']])
            ->assertSessionHasErrors('sections.0');

        $this->assertNull($user->fresh()->settings);
    }
}
