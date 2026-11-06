<?php

use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

describe('Email Tampering Protection', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('administrator');
    });

    it('rejects activation when email in request differs from invitation record', function () {
        // Create invitation for user@example.com
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'user@example.com',
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
            'email' => 'user@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'tamper-token-12345678901234567890123456789012'),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $token = 'tamper-token-12345678901234567890123456789012';

        // Try to activate with different email (attacker trying to hijack)
        $response = $this->postJson("/api/invite/{$token}/activate", [
            'email' => 'attacker@example.com', // Different from invitation record
            'activation_code' => $activationCode,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Email tidak cocok dengan undangan.');

        // Verify user data unchanged
        $user->refresh();
        expect($user->activation_code_hash)->not->toBeNull();
        expect($user->email_verified_at)->toBeNull();
    });

    it('rejects activation when email case differs from invitation record', function () {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'User@Example.COM',
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
            'email' => 'User@Example.COM',
            'role' => 'member',
            'token_hash' => hash('sha256', 'tamper-token-87654321098765432109876543210987'),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $token = 'tamper-token-87654321098765432109876543210987';

        // Try with different case
        $response = $this->postJson("/api/invite/{$token}/activate", [
            'email' => 'user@example.com', // Lowercase version
            'activation_code' => $activationCode,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // Should fail because hash_equals is case-sensitive
        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Email tidak cocok dengan undangan.');
    });

    it('handles email with extra whitespace (trimmed by middleware)', function () {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'user@example.com',
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
            'name' => 'Test User',
            'email' => 'user@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'tamper-token-11111111222222223333333344444444'),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $token = 'tamper-token-11111111222222223333333344444444';

        // Extra whitespace is trimmed by TrimStrings middleware before validation
        // So the email becomes 'user@example.com' which matches
        $response = $this->postJson("/api/invite/{$token}/activate", [
            'email' => ' user@example.com ', // Extra spaces (will be trimmed)
            'activation_code' => $activationCode,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk();
    });

    it('uses persisted invitation email for validation, not client input', function () {
        // This test ensures the server-side validation uses the persisted email
        // from the invitation record, not the email sent in the request

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'real@example.com',
            'password' => Hash::make(str()->password(32)),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->assignRole('member');

        $activationCode = '22222222';
        $expiresAt = now()->addHours(72);
        $activationCodeHash = Hash::make($activationCode);

        $user->forceFill([
            'activation_code_hash' => $activationCodeHash,
            'activation_code_expires_at' => $expiresAt,
            'activation_code_attempts' => 0,
        ])->save();

        $invitation = Invitation::create([
            'name' => 'Test User',
            'email' => 'real@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'persist-token-22222222333333334444444455555555'),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $token = 'persist-token-22222222333333334444444455555555';

        // Even if attacker sends correct code but wrong email
        $response = $this->postJson("/api/invite/{$token}/activate", [
            'email' => 'fake@example.com',
            'activation_code' => $activationCode,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);

        // Now try with correct email but wrong code
        $response2 = $this->postJson("/api/invite/{$token}/activate", [
            'email' => 'real@example.com',
            'activation_code' => 'wrongcode',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response2->assertStatus(422);

        // Finally correct email AND correct code should work
        $response3 = $this->postJson("/api/invite/{$token}/activate", [
            'email' => 'real@example.com',
            'activation_code' => $activationCode,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response3->assertOk();
    });

    it('prevents activation with expired invitation', function () {
        $user = User::factory()->create([
            'name' => 'Expired Test',
            'email' => 'expired@example.com',
            'password' => Hash::make(str()->password(32)),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->assignRole('member');

        $activationCode = '33333333';
        $expiresAt = now()->subHours(1); // Expired
        $activationCodeHash = Hash::make($activationCode);

        $user->forceFill([
            'activation_code_hash' => $activationCodeHash,
            'activation_code_expires_at' => $expiresAt,
            'activation_code_attempts' => 0,
        ])->save();

        $invitation = Invitation::create([
            'name' => 'Expired Test',
            'email' => 'expired@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'expired-token-33333333444444445555555566666666'),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'invited_by' => $this->admin->id,
        ]);

        $token = 'expired-token-33333333444444445555555566666666';

        $response = $this->postJson("/api/invite/{$token}/activate", [
            'email' => 'expired@example.com',
            'activation_code' => $activationCode,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(410);
    });

    it('prevents activation with already accepted invitation', function () {
        $user = User::factory()->create([
            'name' => 'Accepted Test',
            'email' => 'accepted@example.com',
            'password' => Hash::make(str()->password(32)),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->assignRole('member');

        $activationCode = '44444444';
        $expiresAt = now()->addHours(72);
        $activationCodeHash = Hash::make($activationCode);

        $user->forceFill([
            'activation_code_hash' => $activationCodeHash,
            'activation_code_expires_at' => $expiresAt,
            'activation_code_attempts' => 0,
        ])->save();

        $invitation = Invitation::create([
            'name' => 'Accepted Test',
            'email' => 'accepted@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'accepted-token-44444444555555556666666677777777'),
            'expires_at' => $expiresAt,
            'accepted_at' => now(), // Already accepted
            'invited_by' => $this->admin->id,
        ]);

        $token = 'accepted-token-44444444555555556666666677777777';

        $response = $this->postJson("/api/invite/{$token}/activate", [
            'email' => 'accepted@example.com',
            'activation_code' => $activationCode,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(410);
    });
});
