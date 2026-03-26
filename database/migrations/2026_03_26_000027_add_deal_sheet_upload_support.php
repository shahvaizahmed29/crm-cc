<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\Setting;
use App\Models\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('deal_sheet_source_path')->nullable()->after('details');
        });

        $status = Status::firstOrCreate(
            ['slug' => 'deal-sheet-uploaded'],
            ['name' => 'Deal sheet uploaded']
        );

        $adminRole = Role::where('slug', 'admin')->first();
        $agentRole = Role::where('slug', 'agent')->first();
        if ($adminRole) {
            $status->roles()->syncWithoutDetaching([$adminRole->id]);
        }
        if ($agentRole) {
            $status->roles()->syncWithoutDetaching([$agentRole->id]);
        }

        $key = 'holding_status_slugs';
        $row = Setting::query()->where('key', $key)->first();
        if ($row !== null && $row->value !== null && $row->value !== '') {
            $decoded = json_decode($row->value, true);
            if (is_array($decoded) && ! in_array('deal-sheet-uploaded', $decoded, true)) {
                $decoded[] = 'deal-sheet-uploaded';
                Setting::put($key, json_encode(array_values($decoded)));
            }
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('deal_sheet_source_path');
        });
    }
};
