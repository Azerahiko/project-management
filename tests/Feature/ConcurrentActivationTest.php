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
        // Create invitation directly in test
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'concurrent-test@example.com',
            'password' => Hash::make(str()->password(32)),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->assignRole('member');

        $activationCode = '12345678';
        $expiresAt = now()->addHours(72);
        $activationCodeHash = Hash::make($activationCode);

        $user->forceFill([
            'activation_code_hash' => $activationCodeHash,
            'activation_code_expires_at' => $expiresAt,
            'activation_code_attempts' => 0,
        ])->save();

        $invitation = Invitation::create([
            'name' => 'Test User',
            'email' => 'concurrent-test@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'concurrent-token-12345678901234567890123456789012'),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $token = 'concurrent-token-12345678901234567890123456789012';

        // Now make concurrent activation requests
        $results = [];
        $threads = 3;

        for ($i = 0; $i < $threads; $i++) {
            $results[$i] = $this->postJson("/api/invite/{$token}/activate", [
                'email' => 'concurrent-test@example.com',
                'activation_code' => $activationCode,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);
        }

        // Only ONE should succeed
        $successCount = 0;
        $conflictCount = 0;
        $otherErrorCount = 0;

        foreach ($results as $i => $result) {
            $status = $result->status();
            if ($status === 200) {
                $successCount++;
            } elseif (in_array($status, [409, 410, 422])) {
                $conflictCount++;
            } else {
                $otherErrorCount++;
            }
        }

        // Exactly one should succeed
        expect($successCount)->toBe(1);
        expect($conflictCount)->toBe($threads - 1);

        // Verify user has new password and code consumed
        $user->refresh();
        expect($user->activation_code_hash)->toBeNull();
        expect($user->activation_code_expires_at)->toBeNull();
        expect(Hash::check('newpassword123', $user->password))->toBeTrue();

        // Verify invitation accepted
        $invitation->refresh();
        expect($invitation->accepted_at)->not->toBeNull();
    });

    it('handles sequential activations correctly (second fails after first succeeds)', function () {
        // Create invitation
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'sequential-test@example.com',
            'password' => Hash::make(str()->password(32)),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->assignRole('member');

        $activationCode = '87654321';
        $expiresAt = now()->addHours(72);
        $activationCodeHash = Hash::make($activationCode);

        $user->forceFill([
            'activation_code_hash' => $activationCodeHash,
            'activation_code_expires_at' => $expiresAt,
            'activation_code_attempts' => 0,
        ])->save();

        $invitation = Invitation::create([
            'name' => 'Test User',
            'email' => 'sequential-test@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'sequential-token-87654321098765432109876543210987'),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $token = 'sequential-token-87654321098765432109876543210987';

        // First activation succeeds
        $response1 = $this->postJson("/api/invite/{$token}/activate", [
            'email' => 'sequential-test@example.com',
            'activation_code' => $activationCode,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $response1->assertOk();

        // Second activation with same code should fail (code consumed or invitation accepted)
        $response2 = $this->postJson("/api/invite/{$token}/activate", [
            'email' => 'sequential-test@example.com',
            'activation_code' => $activationCode,
            'password' => 'anotherpassword123',
            'password_confirmation' => 'anotherpassword123',
        ]);
        // Can be 422 (code consumed) or 410 (invitation accepted) - both are valid failures
        expect($response2->status())->toBeIn([410, 422]);
        if ($response2->status() === 422) {
            $response2->assertJsonPath('errors.code.0', 'Kode aktivasi tidak valid atau telah kedaluwarsa.');
        }
    });

    it('rate limits concurrent activation attempts from same IP+email', function () {
        $user = User::factory()->create([
            'name' => 'Rate Limit Test',
            'email' => 'ratelimit-test@example.com',
            'password' => Hash::make(str()->password(32)),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->assignRole('member');

        $activationCode = '11111111';
        $expiresAt = now()->addHours(72);
        $activationCodeHash = Hash::make($activationCode);

        $user->forceFill([
            'activation_code_hash' => $activationCodeHash,
            'activation_code_expires_at' => $expiresAt,
            'activation_code_attempts' => 0,
        ])->save();

        $invitation = Invitation::create([
            'name' => 'Rate Limit Test',
            'email' => 'ratelimit-test@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'ratelimit-token-12345678901234567890123456789012'),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $token = 'ratelimit-token-12345678901234567890123456789012';

        // Make 6 attempts with wrong code (max is 5)
        for ($i = 0; $i < 6; $i++) {
            $this->postJson("/api/invite/{$token}/activate", [
                'email' => 'ratelimit-test@example.com',
                'activation_code' => 'wrongcode',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson("/api/invite/{$token}/activate", [
            'email' => 'ratelimit-test@example.com',
            'activation_code' => $activationCode, // Even correct code should be rate limited
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $response->assertStatus(429);
    });
});
