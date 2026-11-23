<?php

use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

describe('Concurrent Activation Protection', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('administrator');
    });

    it('prevents concurrent activation of same invitation via lockForUpdate', function () {
        $token = Str::random(64);

        $user = User::create([
            'name' => 'Concurrent Test User',
            'email' => 'concurrent-test@example.com',
            'password' => 'TemporaryPass123!',
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $invitation = Invitation::create([
            'name' => 'Test User',
            'email' => 'concurrent-test@example.com',
            'role' => 'developer',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(72),
            'accepted_at' => null,
        ]);

        $results = [];

        for ($i = 0; $i < 3; $i++) {
            $results[$i] = $this->postJson("/api/invite/{$token}/activate", []);
        }

        $successCount = 0;
        $conflictCount = 0;

        foreach ($results as $result) {
            $status = $result->status();

            if ($status === 200) {
                $successCount++;
            } elseif (in_array($status, [409, 410, 422])) {
                $conflictCount++;
            }
        }

        expect($successCount)->toBe(1);
        expect($conflictCount)->toBe(2);

        $user = User::first();
        $user->refresh();
        expect($user->email_verified_at)->not->toBeNull();

        $invitation = Invitation::first();
        $invitation->refresh();
    });

    it('handles sequential activations correctly (second fails after first succeeds)', function () {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'sequential-test@example.com',
            'password' => Hash::make('TemporaryPass123!'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->assignRole('developer');

        $expiresAt = now()->addHours(72);
        $token = 'sequential-token-87654321098765432109876543210987';

        $invitation = Invitation::create([
            'name' => 'Test User',
            'email' => 'sequential-test@example.com',
            'role' => 'developer',
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $response1 = $this->postJson("/api/invite/{$token}/activate", [
            'password' => 'ActivationPass123!',
            'password_confirmation' => 'ActivationPass123!',
        ]);
        $response1->assertOk();

        $response2 = $this->postJson("/api/invite/{$token}/activate", [
            'password' => 'ActivationPass123!',
            'password_confirmation' => 'ActivationPass123!',
        ]);
        expect($response2->status())->toBe(410);
    });
});
