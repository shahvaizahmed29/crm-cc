<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number')->nullable();
            $table->string('status')->default('pending');
            $table->text('comment')->nullable();
            $table->string('report_file_path')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['requested_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_reports');
    }
};
