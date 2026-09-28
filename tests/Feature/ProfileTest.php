<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('Profile & Login Email', false);
        $response->assertSee($user->email);
    }

    public function test_profile_information_and_email_can_be_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'Updated Trader Name',
            'email' => 'updated.trader@example.com',
        ]);

        $response->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Updated Trader Name', $user->name);
        $this->assertSame('updated.trader@example.com', $user->email);
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
        ]);

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-secure-password-456',
            'password_confirmation' => 'new-secure-password-456',
        ]);

        $response->assertRedirect('/profile');

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password-456', $user->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
        ]);

        $response = $this->actingAs($user)->from('/profile')->put('/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-secure-password-456',
            'password_confirmation' => 'new-secure-password-456',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }
}
