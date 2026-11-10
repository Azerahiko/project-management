<?php

use App\Mail\InvitationCreated;
use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

describe('End to end Onboarding Flow', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create([
            'email' => 'admin-e2e@example.com',
            'is_active' => true,
        ]);
        $this->admin->assignRole('administrator');
    });

    it('completes the entire onboarding journey from creation to dashboard', function () {
        Mail::fake();

        $tempPassword = 'TemporaryPass123!';
        $createResponse = $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'name' => 'E2E User',
                'email' => 'e2e-user@example.com',
                'temporary_password' => $tempPassword,
                'role' => 'member',
            ])
            ->assertCreated();

        // 1. API does not leak raw token or code
        expect($createResponse->json('activation_code'))->toBeNull();
        expect($createResponse->json('invitation_url'))->toBeNull();

        // 2. Email was dispatched with raw invitation token and temporary password
        $rawToken = null;
        $tempPassword = null;
        Mail::assertSent(InvitationCreated::class, function ($mail) use (&$rawToken, &$tempPassword) {
            $rawToken = $mail->token;
            $tempPassword = $mail->temporaryPassword;

            return $mail->invitation->email === 'e2e-user@example.com' 
                && ! empty($rawToken) 
                && $tempPassword === 'TemporaryPass123!';
        });

        // 3. User attempts login prior to invitation verification -> REJECTED
        $this->app['auth']->guard('web')->logout();
        $this->app['auth']->forgetGuards();

        $earlyLogin = $this->post('/login', [
            'email' => 'e2e-user@example.com',
            'password' => $tempPassword,
        ]);
        $earlyLogin->assertSessionHasErrors('email');
        $this->assertGuest();

        // 4. User opens invitation verification page
        $this->get("/invite/{$rawToken}")->assertOk();

        // 5. User confirms invitation at /api/invite/{token}/activate
        $activateResponse = $this->postJson("/api/invite/{$rawToken}/activate");
        $activateResponse->assertOk();

        $user = User::where('email', 'e2e-user@example.com')->firstOrFail();
        expect($user->email_verified_at)->not->toBeNull();
        expect($user->invitation->isAccepted())->toBeTrue();

        // 6. User logs in using temporary password (verify it works)
        $loginResponse = $this->post('/login', [
            'email' => 'e2e-user@example.com',
            'password' => $tempPassword,
        ]);
        $loginResponse->assertRedirect();
        $this->assertAuthenticatedAs($user);

        // 7. Verify temporary password was included in the email
        expect($tempPassword)->toBe('TemporaryPass123!');

        // 7. Onboarding middleware forces user to /onboarding/password
        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertRedirect('/onboarding');

        $this->get('/onboarding')->assertRedirect('/onboarding/password');

        // 8. User updates password on onboarding
        $newPassword = 'NewSecretPassword123!';
        $passwordUpdate = $this->put('/onboarding/password', [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);
        $passwordUpdate->assertRedirect('/onboarding/two-factor');

        $user->refresh();
        expect($user->onboarding_password_changed_at)->not->toBeNull();
        // Temporary password no longer works
        expect(Hash::check($tempPassword, $user->password))->toBeFalse();
        expect(Hash::check($newPassword, $user->password))->toBeTrue();

        // 9. 2FA setup and confirmation
        $twoFaPage = $this->get('/onboarding/two-factor');
        $twoFaPage->assertOk();

        $user->refresh();
        $secret = decrypt($user->two_factor_secret);
        $engine = app(Google2FA::class);
        $validOtp = $engine->getCurrentOtp($secret);

        $confirm2fa = $this->post('/onboarding/two-factor/confirmed', [
            'code' => $validOtp,
        ]);
        $confirm2fa->assertRedirect('/onboarding/two-factor');

        $user->refresh();
        expect($user->two_factor_confirmed_at)->not->toBeNull();

        // 10. Passkey step (optional, skip to complete)
        $this->get('/onboarding/passkey')->assertOk();

        $completeResponse = $this->post('/onboarding/passkey/skip');
        $completeResponse->assertRedirect('/login');

        // Onboarding completion logs user out and marks completed
        $this->app['auth']->guard('web')->logout();
        $this->app['auth']->forgetGuards();
        $this->assertGuest();
        $user->refresh();
        expect($user->onboarding_completed_at)->not->toBeNull();

        // 11. Subsequent login with new password requires 2FA challenge
        $subsequentLogin = $this->post('/login', [
            'email' => 'e2e-user@example.com',
            'password' => $newPassword,
        ]);
        $subsequentLogin->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        // Pass 2FA challenge
        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        $twoFaChallenge = $this->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
        ])->post('/two-factor-challenge', [
            'recovery_code' => $recoveryCodes[0],
        ]);
        $twoFaChallenge->assertRedirect();
        $this->assertAuthenticatedAs($user);

        // 12. User reaches dashboard!
        $this->get('/dashboard')->assertOk();
    });
});
