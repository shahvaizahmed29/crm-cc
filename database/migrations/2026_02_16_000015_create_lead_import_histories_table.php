<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_import_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('default_status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->string('original_file_name');
            $table->string('original_file_path');
            $table->string('failed_rows_file_path')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->timestamps();

            $table->index(['uploaded_by', 'created_at']);
            $table->index(['default_status_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_import_histories');
    }
};
