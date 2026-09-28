<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EconomicCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/calendar');

        $response->assertOk();
        $response->assertSee('KALENDER EKONOMI');
    }

    public function test_calendar_page_requires_authentication(): void
    {
        $response = $this->get('/calendar');

        $response->assertRedirect('/login');
    }

    public function test_events_data_endpoint_returns_expected_json_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/calendar/events-data');

        $response->assertOk();
        $response->assertJsonStructure([
            'events',
            'next_high_impact',
            'is_live',
            'server_time',
        ]);
    }

    public function test_events_data_rejects_invalid_currency(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/calendar/events-data?currency=XYZ');

        $response->assertStatus(422);
    }
}
