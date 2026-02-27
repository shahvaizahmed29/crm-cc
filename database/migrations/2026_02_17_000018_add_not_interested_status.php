<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $statusId = DB::table('statuses')->where('slug', 'not-interested')->value('id');

        if (! $statusId) {
            $statusId = DB::table('statuses')->insertGetId([
                'name' => 'Not Interested',
                'slug' => 'not-interested',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['admin', 'agent'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('status_role')
                ->where('status_id', $statusId)
                ->where('role_id', $roleId)
                ->exists();

            if (! $exists) {
                DB::table('status_role')->insert([
                    'status_id' => $statusId,
                    'role_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $statusId = DB::table('statuses')->where('slug', 'not-interested')->value('id');
        if (! $statusId) {
            return;
        }

        DB::table('status_role')->where('status_id', $statusId)->delete();
        DB::table('statuses')->where('id', $statusId)->delete();
    }
};

