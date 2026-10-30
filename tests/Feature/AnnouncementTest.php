<?php

require_once __DIR__.'/helpers.php';

use App\Models\Announcement;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', ['--force' => true]);
    (new RolePermissionSeeder)->run();
});

it('allows members to read announcements but not manage them', function () {
    [$admin, , $member] = createTestUsers();

    Announcement::factory()->create([
        'user_id' => $admin->id,
        'title' => 'Informasi Sprint',
    ]);

    $response = $this->actingAs($member)->get(route('announcements.index'));

    $response->assertOk();

    expect($response->inertiaProps('can_manage'))->toBeFalse()
        ->and(collect($response->inertiaProps('announcements.data'))->pluck('title')->all())
        ->toContain('Informasi Sprint');
});

it('allows project managers to create announcements', function () {
    [, $pm] = createTestUsers();

    $response = $this->actingAs($pm)->post(route('announcements.store'), [
        'title' => 'Pengingat Sprint Review',
        'body' => 'Sprint review hari Jumat pukul 14.00 WIB.',
        'is_pinned' => true,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('announcements', [
        'title' => 'Pengingat Sprint Review',
        'is_pinned' => true,
    ]);
});

it('validates announcement input', function () {
    [, $pm] = createTestUsers();

    $this->actingAs($pm)
        ->post(route('announcements.store'), [
            'title' => '',
            'body' => '',
        ])
        ->assertSessionHasErrors(['title', 'body']);
});

it('forbids members from creating announcements', function () {
    [, , $member] = createTestUsers();

    $this->actingAs($member)
        ->post(route('announcements.store'), [
            'title' => 'Tidak boleh',
            'body' => 'Anggota biasa tidak boleh membuat pengumuman.',
        ])
        ->assertForbidden();
});

it('allows project managers to pin and delete announcements', function () {
    [$admin, $pm] = createTestUsers();

    $announcement = Announcement::factory()->create([
        'user_id' => $admin->id,
        'is_pinned' => false,
    ]);

    $this->actingAs($pm)
        ->patch(route('announcements.pin', $announcement))
        ->assertRedirect();

    expect($announcement->fresh()->is_pinned)->toBeTrue();

    $this->actingAs($pm)
        ->delete(route('announcements.destroy', $announcement))
        ->assertRedirect();

    $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
});
