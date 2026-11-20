<?php

require_once __DIR__.'/helpers.php';

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('guards placeholder pages with their related permissions', function () {
    $developer = User::factory()->create(['is_active' => true]);
    $developer->assignRole('developer');

    $this->actingAs($developer)->get('/collaboration')->assertOk();
    $this->actingAs($developer)->get('/calendar')->assertOk();
    $this->actingAs($developer)->get('/documents')->assertOk();

    $this->actingAs($developer)->get('/teams')->assertForbidden();
    $this->actingAs($developer)->get('/reports')->assertForbidden();
    $this->actingAs($developer)->get('/activity-log')->assertForbidden();
});

it('denies clients access to the notifications page', function () {
    $client = User::factory()->create(['is_active' => true]);
    $client->assignRole('client');

    $this->actingAs($client)->get('/notifications')->assertForbidden();
});

it('allows project managers to access monitoring pages', function () {
    $projectManager = User::factory()->create(['is_active' => true]);
    $projectManager->assignRole('project_manager');

    $this->actingAs($projectManager)->get('/reports')->assertOk();
    $this->actingAs($projectManager)->get('/activity-log')->assertOk();
    $this->actingAs($projectManager)->get('/teams')->assertOk();
});

it('allows every authenticated role to access personal settings and support', function () {
    foreach (['developer', 'reviewer', 'client', 'team_leader', 'project_manager'] as $role) {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        $this->actingAs($user)->get('/settings')->assertOk();
        $this->actingAs($user)->get('/support')->assertOk();
    }
});
