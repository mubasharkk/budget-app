<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_lists_creates_updates_and_deletes_own_providers(): void
    {
        $user = User::factory()->create();
        $vodafone = Provider::factory()->for($user)->create(['name' => 'Vodafone']);
        Contract::factory()->for($user)->create(['provider_id' => $vodafone->id]);
        Provider::factory()->create(['name' => 'Someone elses']);
        $this->actingAs($user);

        $this->get(route('providers.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Providers/Index')
                ->has('providers', 1)
                ->where('providers.0.contracts_count', 1));
        $this->get(route('providers.create'))->assertInertia(fn ($page) => $page->component('Providers/Create'));
        $this->get(route('providers.edit', $vodafone))->assertInertia(fn ($page) => $page->where('provider.id', $vodafone->id));

        $this->post(route('providers.store'), ['name' => 'Telekom', 'website' => 'https://telekom.de'])
            ->assertRedirect(route('providers.index'));
        $this->assertDatabaseHas('providers', ['user_id' => $user->id, 'name' => 'Telekom']);

        $this->put(route('providers.update', $vodafone), ['name' => 'Vodafone DE'])
            ->assertRedirect(route('providers.index'));
        $this->assertSame('Vodafone DE', $vodafone->fresh()->name);

        $this->delete(route('providers.destroy', $vodafone))->assertRedirect(route('providers.index'));
        $this->assertModelMissing($vodafone);
    }

    public function test_provider_input_is_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('providers.store'), ['name' => '', 'website' => 'not-a-url', 'contact_email' => 'nope'])
            ->assertSessionHasErrors([
                'name' => 'A provider name is required.',
                'website' => 'The website must be a valid URL (including http:// or https://).',
                'contact_email' => 'Please enter a valid contact email address.',
            ]);
    }

    public function test_user_cannot_touch_another_users_provider(): void
    {
        $provider = Provider::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('providers.edit', $provider))->assertForbidden();
        $this->put(route('providers.update', $provider), ['name' => 'Hijacked'])->assertForbidden();
        $this->delete(route('providers.destroy', $provider))->assertForbidden();
        $this->assertModelExists($provider);
    }
}
