<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_guest_is_redirected_to_login_screen(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_accesses_dashboard(): void
    {
        $user = User::factory()->create();
        Account::create([
            'user_id' => $user->id,
            'name' => 'Primary Test Account',
            'initial_balance' => 5000,
            'current_balance' => 5000,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);
    }
}
