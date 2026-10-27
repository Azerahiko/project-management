<?php

require_once __DIR__.'/helpers.php';

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', ['--force' => true]);
    (new RolePermissionSeeder)->run();
});

it('guards placeholder pages with their related permissions', function () {
    $member = User::factory()->create(['is_active' => true]);
    $member->assignRole('member');

    $this->actingAs($member)->get('/collaboration')->assertOk();
    $this->actingAs($member)->get('/calendar')->assertOk();
    $this->actingAs($member)->get('/documents')->assertOk();

    $this->actingAs($member)->get('/teams')->assertForbidden();
    $this->actingAs($member)->get('/reports')->assertForbidden();
    $this->actingAs($member)->get('/activity-log')->assertForbidden();
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
