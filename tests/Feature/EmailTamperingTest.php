<?php

use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

describe('Invitation Verification Boundary', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('administrator');
    });

    it('verifies email based exclusively on the persisted invitation record', function () {
        $user = User::factory()->create([
            'name' => 'Persisted User',
            'email' => 'persisted@example.com',
            'password' => Hash::make('TemporaryPass123!'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->assignRole('member');

        $expiresAt = now()->addHours(72);
        $token = 'secure-token-12345678901234567890123456789012';

        $invitation = Invitation::create([
            'name' => 'Persisted User',
            'email' => 'persisted@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $response = $this->postJson("/api/invite/{$token}/activate");
        $response->assertOk();

        $user->refresh();
        $invitation->refresh();
        expect($user->email_verified_at)->not->toBeNull();
        expect($invitation->accepted_at)->not->toBeNull();
    });

    it('prevents activation with expired invitation', function () {
        $user = User::factory()->create([
            'name' => 'Expired User',
            'email' => 'expired-user@example.com',
            'password' => Hash::make('TemporaryPass123!'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->assignRole('member');

        $expiresAt = now()->subHour();
        $token = 'expired-token-12345678901234567890123456789012';

        $invitation = Invitation::create([
            'name' => 'Expired User',
            'email' => 'expired-user@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $response = $this->postJson("/api/invite/{$token}/activate");
        $response->assertStatus(410);

        $user->refresh();
        expect($user->email_verified_at)->toBeNull();
    });

    it('prevents activation with already accepted invitation', function () {
        $user = User::factory()->create([
            'name' => 'Accepted User',
            'email' => 'already-accepted@example.com',
            'password' => Hash::make('TemporaryPass123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('member');

        $expiresAt = now()->addHours(72);
        $token = 'accepted-token-12345678901234567890123456789012';

        $invitation = Invitation::create([
            'name' => 'Accepted User',
            'email' => 'already-accepted@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'accepted_at' => now(),
            'invited_by' => $this->admin->id,
        ]);

        $response = $this->postJson("/api/invite/{$token}/activate");
        $response->assertStatus(410);
    });
});
