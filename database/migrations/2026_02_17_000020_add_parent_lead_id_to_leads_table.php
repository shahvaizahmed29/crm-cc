<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            if (! Schema::hasColumn('leads', 'parent_lead_id')) {
                $table->foreignId('parent_lead_id')
                    ->nullable()
                    ->after('assigned_to')
                    ->constrained('leads')
                    ->nullOnDelete();
                $table->index('parent_lead_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            if (Schema::hasColumn('leads', 'parent_lead_id')) {
                $table->dropConstrainedForeignId('parent_lead_id');
            }
        });
    }
};

