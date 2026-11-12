<?php

require_once __DIR__.'/helpers.php';

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function incompleteUser(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'is_active' => true,
        'password' => Hash::make('TemporaryPass1!'),
        'email_verified_at' => now(),
        'onboarding_password_changed_at' => null,
        'two_factor_confirmed_at' => null,
        'onboarding_completed_at' => null,
    ], $overrides));

    $user->assignRole('member');

    return $user;
}

describe('Onboarding gate', function () {
    it('redirects incomplete users away from protected web routes', function () {
        $user = incompleteUser();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/onboarding');
        $this->actingAs($user)->get('/settings')->assertRedirect('/onboarding');
    });

    it('returns 403 JSON to API clients with incomplete onboarding', function () {
        $user = incompleteUser();

        $response = $this->actingAs($user)->getJson('/api/projects');

        $response->assertForbidden()
            ->assertJsonPath('error', 'onboarding_required');
    });

    it('lets completed users through to protected routes', function () {
        $user = incompleteUser([
            'onboarding_password_changed_at' => now(),
            'two_factor_confirmed_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    });
});

describe('Onboarding step gates', function () {
    it('blocks the two-factor step until the password is changed', function () {
        $user = incompleteUser();

        $this->actingAs($user)
            ->get('/onboarding/two-factor')
            ->assertRedirect('/onboarding/password');
    });

    it('blocks the passkey step until two-factor is confirmed', function () {
        $user = incompleteUser([
            'onboarding_password_changed_at' => now(),
            'two_factor_confirmed_at' => null,
        ]);

        $this->actingAs($user)
            ->get('/onboarding/passkey')
            ->assertRedirect('/onboarding/two-factor');
    });

    it('refuses to complete onboarding without two-factor confirmation', function () {
        $user = incompleteUser(['onboarding_password_changed_at' => now()]);

        $this->actingAs($user)
            ->post('/onboarding/complete')
            ->assertRedirect('/onboarding/two-factor');

        expect($user->fresh()->onboarding_completed_at)->toBeNull();
    });

    it('refuses to skip the passkey step without two-factor confirmation', function () {
        $user = incompleteUser(['onboarding_password_changed_at' => now()]);

        $this->actingAs($user)
            ->post('/onboarding/passkey/skip')
            ->assertRedirect('/onboarding/two-factor');

        expect($user->fresh()->onboarding_completed_at)->toBeNull();
    });
});
