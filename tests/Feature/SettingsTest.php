<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function settingsUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');

    return $user;
}

it('redirects guests away from settings', function () {
    $this->get('/settings')->assertRedirect('/login');
});

it('renders the settings page with account and notification data', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->where('account.name', $user->name)
            ->where('account.email', $user->email)
            ->where('account.email_verified', true)
            ->where('account.roles', ['member'])
            ->has('notification_preferences')
            ->has('notification_options')
            ->where('notification_preferences.task_updates', true)
            ->where('notification_preferences.daily_digest', false)
        );
});

it('updates the profile without clearing email verification', function () {
    $user = settingsUser();
    $verifiedAt = $user->email_verified_at?->toDateTimeString();

    $this->actingAs($user)
        ->put('/settings/profile', [
            'name' => 'Nama Diperbarui',
            'email' => 'diperbarui@example.test',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->name)->toBe('Nama Diperbarui')
        ->and($user->email)->toBe('diperbarui@example.test')
        ->and($user->email_verified_at?->toDateTimeString())->toBe($verifiedAt);
});

it('normalizes the email address when updating the profile', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->put('/settings/profile', [
            'name' => '  Nama Rapi  ',
            'email' => '  Diperbarui@Example.TEST  ',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->fresh()->name)->toBe('Nama Rapi')
        ->and($user->fresh()->email)->toBe('diperbarui@example.test');
});

it('rejects a profile update with an email owned by another user', function () {
    $user = settingsUser();
    User::factory()->create(['email' => 'sudah-dipakai@example.test']);

    $this->actingAs($user)
        ->put('/settings/profile', [
            'name' => $user->name,
            'email' => 'sudah-dipakai@example.test',
        ])
        ->assertSessionHasErrors('email');

    expect($user->fresh()->email)->not->toBe('sudah-dipakai@example.test');
});

it('rejects an invalid profile email', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->put('/settings/profile', [
            'name' => $user->name,
            'email' => 'bukan-email',
        ])
        ->assertSessionHasErrors('email');
});

it('changes the password when the current password matches', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'kata-sandi-baru',
            'password_confirmation' => 'kata-sandi-baru',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Hash::check('kata-sandi-baru', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('password', $user->fresh()->password))->toBeFalse();
});

it('rejects a password change when the current password is wrong', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->put('/settings/password', [
            'current_password' => 'salah',
            'password' => 'kata-sandi-baru',
            'password_confirmation' => 'kata-sandi-baru',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('validates password confirmation and minimum length', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'pendek',
            'password_confirmation' => 'beda',
        ])
        ->assertSessionHasErrors('password');
});

it('persists notification preferences and ignores unknown keys', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->put('/settings/notifications', [
            'preferences' => [
                'task_updates' => false,
                'daily_digest' => true,
                'unknown_option' => true,
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $preferences = $user->fresh()->notificationPreferences();

    expect($preferences['task_updates'])->toBeFalse()
        ->and($preferences['daily_digest'])->toBeTrue()
        ->and($preferences['mentions'])->toBeTrue()
        ->and($preferences)->not->toHaveKey('unknown_option');
});

it('keeps unspecified notification preferences when saving a subset', function () {
    $user = settingsUser();
    $user->forceFill([
        'notification_preferences' => [
            'task_updates' => false,
            'mentions' => false,
        ],
    ])->save();

    $this->actingAs($user)
        ->put('/settings/notifications', [
            'preferences' => [
                'daily_digest' => true,
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $preferences = $user->fresh()->notificationPreferences();

    expect($preferences['task_updates'])->toBeFalse()
        ->and($preferences['mentions'])->toBeFalse()
        ->and($preferences['daily_digest'])->toBeTrue();
});

it('rejects non-boolean notification values', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->put('/settings/notifications', [
            'preferences' => [
                'task_updates' => 'ya',
            ],
        ])
        ->assertSessionHasErrors('preferences.task_updates');
});
