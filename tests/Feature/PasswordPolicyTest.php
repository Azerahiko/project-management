<?php

require_once __DIR__.'/helpers.php';

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->create([
        'is_active' => true,
        'password' => Hash::make('CurrentPass123!'),
    ]);
    $this->user->assignRole('administrator');
});

it('applies Password::defaults() when updating password in settings', function () {
    // Enforce a policy the raw 'min:8' rule would not catch.
    Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

    $this->actingAs($this->user)
        ->put('/settings/password', [
            'current_password' => 'CurrentPass123!',
            'password' => 'short1!', // 8 chars: passes min:8, fails policy
            'password_confirmation' => 'short1!',
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check('CurrentPass123!', $this->user->fresh()->password))->toBeTrue();

    Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

    $this->actingAs($this->user)
        ->put('/settings/password', [
            'current_password' => 'CurrentPass123!',
            'password' => 'BrandNewPass123!',
            'password_confirmation' => 'BrandNewPass123!',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('BrandNewPass123!', $this->user->fresh()->password))->toBeTrue();
});

it('shares the configured password minimum length with the frontend', function () {
    config(['auth.password_min_length' => 14]);

    $response = $this->actingAs($this->user)->get('/settings');

    $response->assertOk();
    expect($response->inertiaProps('password_min_length'))->toBe(14);
});

it('keeps the generated temporary password compliant with the password policy', function () {
    Password::defaults(fn () => Password::min(16)->mixedCase()->numbers()->symbols());

    $temporaryPassword = Str::password(16);

    $validator = validator(
        ['password' => $temporaryPassword],
        ['password' => Password::defaults()],
    );

    expect($validator->passes())->toBeTrue();
});
