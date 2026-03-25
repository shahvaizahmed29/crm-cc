<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->unsignedBigInteger('skipped_at_sequence')->nullable()->after('is_dnc');
            $table->index('skipped_at_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['skipped_at_sequence']);
            $table->dropColumn('skipped_at_sequence');
        });
    }
};
