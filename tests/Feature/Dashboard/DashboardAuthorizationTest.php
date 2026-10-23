<?php

require_once __DIR__.'/../helpers.php';

use App\Models\User;
use App\Support\Roles;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', ['--force' => true]);
    (new RolePermissionSeeder)->run();
});

test('guest redirected to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('user without dashboard.view permission gets 403', function () {
    // Create user with a role that doesn't have dashboard.view permission
    // Use a custom role or just don't assign any role with that permission
    $user = User::factory()->create([
        'is_active' => true,
    ]);
    // Don't assign any role, so they don't have dashboard.view
    // But they need to be authenticated, so we use actingAs

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
});

foreach ([
    Roles::ADMINISTRATOR,
    Roles::PROJECT_MANAGER,
    Roles::TEAM_LEADER,
    Roles::MEMBER,
    Roles::REVIEWER,
    Roles::CLIENT,
] as $role) {
    test("{$role} can access dashboard", function () use ($role) {
        $user = User::factory()->create([
            'is_active' => true,
        ])->assignRole($role);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    });
}
