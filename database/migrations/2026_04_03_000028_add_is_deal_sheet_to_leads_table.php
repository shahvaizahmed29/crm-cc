<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->boolean('is_deal_sheet')->default(false)->after('deal_sheet_source_path');
            $table->index('is_deal_sheet');
        });

        $dealSheetStatusId = DB::table('statuses')->where('slug', 'deal-sheet-uploaded')->value('id');
        $query = DB::table('leads');
        if ($dealSheetStatusId !== null) {
            $query->where(function ($nested) use ($dealSheetStatusId): void {
                $nested->where('status_id', (int) $dealSheetStatusId)
                    ->orWhereNotNull('deal_sheet_source_path');
            });
        } else {
            $query->whereNotNull('deal_sheet_source_path');
        }
        $query->update(['is_deal_sheet' => true]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['is_deal_sheet']);
            $table->dropColumn('is_deal_sheet');
        });
    }
};
