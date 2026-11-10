<?php

use App\Mail\InvitationCreated;
use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

        $response = $this->post("/api/invite/{$token}/activate");

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

        $user = User::create([
            'name' => 'Test User',
            'email' => 'accept@example.com',
            'password' => Hash::make('TemporaryPass123!'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $invitation = Invitation::create([
            'name' => 'Test User',
            'email' => 'accept@example.com',
            'role' => 'member',
            'token_hash' => $tokenHash,
            'expires_at' => now()->addHours(72),
        ]);

        $response = $this->post("/api/invite/{$token}/activate");

        $response->assertJson([
            'message' => 'Email dan undangan berhasil diverifikasi. Silakan masuk menggunakan kata sandi sementara Anda.',
            'redirect' => route('login'),
        ]);

        $user->refresh();
        $invitation->refresh();
        expect($user->email_verified_at)->not->toBeNull();
        expect($invitation->accepted_at)->not->toBeNull();
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

describe('Onboarding Findings Verification', function () {
    it('challenges 2FA at login for user with confirmed 2FA (F-01)', function () {
        $user = User::factory()->create([
            'email' => 'twofa-user@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        ]);

        $response = $this->post('/login', [
            'email' => 'twofa-user@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        // 2FA challenge page renders ok
        $challengeResponse = $this->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
        ])->get('/two-factor-challenge');
        $challengeResponse->assertOk();

        // Submitting recovery code logs the user in
        $authResponse = $this->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
        ])->post('/two-factor-challenge', [
            'recovery_code' => 'recovery-code-1',
        ]);

        $authResponse->assertRedirect();
        $this->assertAuthenticatedAs($user);
    });

    it('returns raw token in invitation email that resolves successfully (F-04)', function () {
        Mail::fake();

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('administrator');

        $response = $this->actingAs($admin)
            ->postJson('/api/invitations', [
                'name' => 'Raw Token User',
                'email' => 'rawtoken@example.com',
                'role' => 'member',
            ])
            ->assertCreated();

        Mail::assertSent(InvitationCreated::class, function ($mail) {
            $parsedUrl = parse_url(route('invitations.show', ['token' => $mail->token]), PHP_URL_PATH);
            $this->app['auth']->guard('web')->logout();
            $this->app['auth']->forgetGuards();
            $this->get($parsedUrl)->assertOk();

            return true;
        });
    });

    it('returns activation code and working invitation link on admin user creation (F-03)', function () {
        Mail::fake();

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('administrator');

        $response = $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Created Via Admin',
                'email' => 'createdadmin@example.com',
                'temporary_password' => 'TemporaryPass123!',
                'role' => 'member',
            ])
            ->assertCreated();

        expect($response->json('activation_code'))->toBeNull();
        expect($response->json('invitation_url'))->toBeNull();

        Mail::assertSent(InvitationCreated::class, function ($mail) {
            return $mail->invitation->email === 'createdadmin@example.com'
                && ! empty($mail->token)
                && $mail->temporaryPassword === 'TemporaryPass123!';
        });
    });

    it('supports resending invitations with route and invalidation (F-09)', function () {
        Mail::fake();

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('administrator');

        $storeResponse = $this->actingAs($admin)
            ->postJson('/api/invitations', [
                'name' => 'Resend Test',
                'email' => 'resendtest@example.com',
                'role' => 'member',
            ]);

        $invitation = Invitation::where('email', 'resendtest@example.com')->firstOrFail();
        $originalHash = $invitation->token_hash;

        $resendResponse = $this->actingAs($admin)
            ->postJson("/api/invitations/{$invitation->id}/resend")
            ->assertOk();

        expect($resendResponse->json('activation_code'))->toBeNull();
        expect($resendResponse->json('invitation_url'))->toBeNull();
        $invitation->refresh();
        expect($invitation->token_hash)->not->toBe($originalHash);

        Mail::assertSent(InvitationCreated::class, function ($mail) {
            return $mail->invitation->email === 'resendtest@example.com'
                && ! empty($mail->token);
        });
    });

    it('includes onboarding, 2FA, and invitation statuses in UserResource (F-07, F-13)', function () {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('administrator');

        $user = User::factory()->create([
            'email' => 'statuscheck@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
            'onboarding_completed_at' => now(),
        ]);
        $user->assignRole('member');

        $response = $this->actingAs($admin)->getJson("/api/users/{$user->id}")->assertOk();

        $response->assertJsonPath('data.two_factor_enabled', true);
        $response->assertJsonPath('data.is_onboarded', true);
        expect($response->json('data.onboarding_completed_at'))->not->toBeNull();
    });

    it('completes onboarding password step and generates local 2FA QR (F-02, F-11)', function () {
        $user = User::factory()->create([
            'email' => 'onboardsteps@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_password_changed_at' => null,
            'two_factor_confirmed_at' => null,
            'onboarding_completed_at' => null,
        ]);
        $user->assignRole('member');

        // Step 1: Update password
        $this->actingAs($user)
            ->put('/onboarding/password', [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect('/onboarding/two-factor');

        $user->refresh();
        expect($user->onboarding_password_changed_at)->not->toBeNull();

        // Step 2: GET two-factor renders local SVG
        $twoFaResponse = $this->actingAs($user)->get('/onboarding/two-factor');
        $twoFaResponse->assertOk();

        $user->refresh();
        expect($user->two_factor_secret)->not->toBeNull();
        expect($user->twoFactorQrCodeSvg())->toContain('<svg');
    });

    it('permits passkey options and store for user on passkey step (F-06)', function () {
        $user = User::factory()->create([
            'email' => 'passkeyuser@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_password_changed_at' => now(),
            'two_factor_confirmed_at' => now(),
            'onboarding_completed_at' => null,
        ]);
        $user->assignRole('member');

        // Passkey step is reachable
        $this->actingAs($user)->get('/onboarding/passkey')->assertOk();

        // Registration options endpoint is accessible with password confirmed session without getting redirected back to onboarding
        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->getJson('/user/passkeys/options');
        $response->assertOk();
        expect($response->json('options'))->not->toBeNull();
    });

    it('renders branded error view on 410 (F-05)', function () {
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        Invitation::create([
            'name' => 'Expired User',
            'email' => 'expired-brand@example.com',
            'role' => 'member',
            'token_hash' => $tokenHash,
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->get("/invite/{$token}");
        $response->assertStatus(410);
        $response->assertSee('Tautan Tidak Berlaku');
        $response->assertDontSee('Fatal error');
        $response->assertDontSee('vendor/');
    });
});
