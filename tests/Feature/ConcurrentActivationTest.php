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
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'concurrent-test@example.com',
            'password' => Hash::make('TemporaryPass123!'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->assignRole('member');

        $expiresAt = now()->addHours(72);
        $token = 'concurrent-token-12345678901234567890123456789012';

        $invitation = Invitation::create([
            'name' => 'Test User',
            'email' => 'concurrent-test@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $results = [];
        $threads = 3;

        for ($i = 0; $i < $threads; $i++) {
            $results[$i] = $this->postJson("/api/invite/{$token}/activate");
        }

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

        expect($successCount)->toBe(1);
        expect($conflictCount)->toBe($threads - 1);

        $user->refresh();
        expect($user->email_verified_at)->not->toBeNull();
        expect(Hash::check('TemporaryPass123!', $user->password))->toBeTrue();

        $invitation->refresh();
        expect($invitation->accepted_at)->not->toBeNull();
    });

    it('handles sequential activations correctly (second fails after first succeeds)', function () {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'sequential-test@example.com',
            'password' => Hash::make('TemporaryPass123!'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->assignRole('member');

        $expiresAt = now()->addHours(72);
        $token = 'sequential-token-87654321098765432109876543210987';

        $invitation = Invitation::create([
            'name' => 'Test User',
            'email' => 'sequential-test@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $response1 = $this->postJson("/api/invite/{$token}/activate");
        $response1->assertOk();

        $response2 = $this->postJson("/api/invite/{$token}/activate");
        expect($response2->status())->toBe(410);
    });
});
