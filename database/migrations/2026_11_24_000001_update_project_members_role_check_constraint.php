<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Migrasikan data existing sebelum constraint diperbarui
        // Viewer -> Client
        DB::table('project_members')
            ->where('role', 'viewer')
            ->update(['role' => 'client']);

        // Developer yang memiliki global role 'reviewer' -> Reviewer
        DB::table('project_members')
            ->where('role', 'developer')
            ->whereIn('user_id', function ($query) {
                $query->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'reviewer');
            })
            ->update(['role' => 'reviewer']);

        // Sisa developer -> Frontend
        DB::table('project_members')
            ->where('role', 'developer')
            ->update(['role' => 'frontend']);

        // 2. Perbarui PostgreSQL CHECK constraint
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE project_members DROP CONSTRAINT IF EXISTS project_members_role_check');
            DB::statement("ALTER TABLE project_members ADD CONSTRAINT project_members_role_check CHECK (role::text = ANY (ARRAY['owner'::text, 'manager'::text, 'frontend'::text, 'backend'::text, 'fullstack'::text, 'ui_ux'::text, 'reviewer'::text, 'client'::text]))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE project_members DROP CONSTRAINT IF EXISTS project_members_role_check');
            DB::statement("ALTER TABLE project_members ADD CONSTRAINT project_members_role_check CHECK (role::text = ANY (ARRAY['owner'::text, 'manager'::text, 'developer'::text, 'viewer'::text]))");
        }
    }
};
