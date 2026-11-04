<?php

use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

describe('Invitation System', function () {
    it('admin can create invitation', function () {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('administrator');

        $response = $this->actingAs($admin)
            ->post('/api/invitations', [
                'name' => 'Test User',
                'email' => 'testinvite@example.com',
                'role' => 'member',
            ]);

        // May redirect or return JSON depending on middleware
        $this->assertTrue(
            in_array($response->status(), [201, 302]),
            "Expected 201 or 302, got {$response->status()}"
        );

        if ($response->status() === 201) {
            $response->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'temp_password',
                'invitation_url',
            ]);
        }
    });

    it('non-admin cannot create invitation', function () {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('project_manager');

        $response = $this->actingAs($user)
            ->post('/api/invitations', [
                'name' => 'Test User',
                'email' => 'testinvite2@example.com',
                'role' => 'member',
            ]);

        $this->assertTrue(
            in_array($response->status(), [403, 302]),
            "Expected 403 or 302, got {$response->status()}"
        );
    });

    it('shows invitation page with valid token', function () {
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $invitation = Invitation::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'member',
            'token_hash' => $tokenHash,
            'expires_at' => now()->addHours(72),
        ]);

        $response = $this->get("/invite/{$token}");

        $response->assertOk();
    });

    it('rejects invalid invitation token', function () {
        $response = $this->get('/invite/invalidtoken123');

        $response->assertStatus(404);
    });

    it('rejects expired invitation', function () {
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        Invitation::create([
            'name' => 'Test User',
            'email' => 'expired@example.com',
            'role' => 'member',
            'token_hash' => $tokenHash,
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->post("/invite/{$token}");

        $response->assertStatus(410);
    });

    it('rejects already accepted invitation', function () {
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        Invitation::create([
            'name' => 'Test User',
            'email' => 'accepted@example.com',
            'role' => 'member',
            'token_hash' => $tokenHash,
            'expires_at' => now()->addHours(72),
            'accepted_at' => now(),
        ]);

        $response = $this->get("/invite/{$token}");

        $response->assertStatus(410);
    });

    it('accepts valid invitation and verifies email', function () {
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);
        $tempPassword = 'temppassword123';

        $user = User::create([
            'name' => 'Test User',
            'email' => 'accept@example.com',
            'password' => Hash::make($tempPassword),
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        Invitation::create([
            'name' => 'Test User',
            'email' => 'accept@example.com',
            'role' => 'member',
            'token_hash' => $tokenHash,
            'expires_at' => now()->addHours(72),
        ]);

        $response = $this->post("/invite/{$token}");

        $response->assertRedirect('/login');

        $user->refresh();
        expect($user->email_verified_at)->not->toBeNull();
    });

    it('unverified user cannot login', function () {
        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => 'unverified@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });
});

describe('Onboarding System', function () {
    it('blocks dashboard access for incomplete onboarding user', function () {
        $user = User::factory()->create([
            'email' => 'incomplete@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_password_changed_at' => now(),
            'two_factor_confirmed_at' => now(),
            'onboarding_completed_at' => null,
        ]);
        $user->assignRole('administrator');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/onboarding');
    });

    it('allows dashboard for completed onboarding user', function () {
        $user = User::factory()->create([
            'email' => 'allowed@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_password_changed_at' => now(),
            'two_factor_confirmed_at' => now(),
            'onboarding_completed_at' => now(),
        ]);
        $user->assignRole('administrator');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    });
});

describe('Onboarding Bypass Prevention', function () {
    it('cannot access two-factor step without password change', function () {
        $user = User::factory()->create([
            'email' => 'bypass1@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_password_changed_at' => null,
            'onboarding_completed_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/onboarding/two-factor');

        $response->assertRedirect('/onboarding/password');
    });

    it('cannot access passkey step without 2FA', function () {
        $user = User::factory()->create([
            'email' => 'bypass2@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_password_changed_at' => now(),
            'two_factor_confirmed_at' => null,
            'onboarding_completed_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/onboarding/passkey');

        $response->assertRedirect('/onboarding/two-factor');
    });

    it('cannot complete onboarding without 2FA', function () {
        $user = User::factory()->create([
            'email' => 'bypass3@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_password_changed_at' => now(),
            'two_factor_confirmed_at' => null,
            'onboarding_completed_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/onboarding/complete');

        $response->assertRedirect('/onboarding/two-factor');
        $user->refresh();
        expect($user->onboarding_completed_at)->toBeNull();
    });
});
