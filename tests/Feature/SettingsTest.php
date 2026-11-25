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
    $user->assignRole('developer');

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
            ->where('account.roles', ['developer'])
            ->has('notification_preferences')
            ->has('notification_options')
            ->where('notification_preferences.task_updates', true)
            ->where('notification_preferences.daily_digest', false)
        );
});

it('rejects name change via settings profile endpoint', function () {
    $user = settingsUser();
    $originalName = $user->name;

    $this->actingAs($user)
        ->put('/settings/profile', [
            'name' => 'Nama Diperbarui',
            'email' => $user->email,
        ])
        ->assertSessionHasErrors('name');

    expect($user->fresh()->name)->toBe($originalName);
});

it('rejects email change via settings profile endpoint', function () {
    $user = settingsUser();
    $originalEmail = $user->email;

    $this->actingAs($user)
        ->put('/settings/profile', [
            'name' => $user->name,
            'email' => 'diperbarui@example.test',
        ])
        ->assertSessionHasErrors('email');

    expect($user->fresh()->email)->toBe($originalEmail);
});

it('allows profile endpoint when name and email are unchanged', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->put('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->fresh()->name)->toBe($user->name)
        ->and($user->fresh()->email)->toBe($user->email);
});

it('rejects email change with invalid format via settings', function () {
    $user = settingsUser();
    $originalEmail = $user->email;

    $this->actingAs($user)
        ->put('/settings/profile', [
            'name' => $user->name,
            'email' => 'bukan-email',
        ])
        ->assertSessionHasErrors('email');

    expect($user->fresh()->email)->toBe($originalEmail);
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
