<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $columns = ['callback_at', 'callback_created_by', 'callback_notified_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            if (! Schema::hasColumn('leads', 'callback_at')) {
                $table->dateTime('callback_at')->nullable()->after('ssn');
                $table->foreignId('callback_created_by')->nullable()->after('callback_at')->constrained('users')->nullOnDelete();
                $table->dateTime('callback_notified_at')->nullable()->after('callback_created_by');
            }
        });
    }
};
