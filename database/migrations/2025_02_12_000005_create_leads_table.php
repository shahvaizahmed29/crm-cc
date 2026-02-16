<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('mothers_maiden_name')->nullable();
            $table->string('ssn')->nullable();
            $table->decimal('approx_debt', 12, 2)->nullable();
            $table->text('details')->nullable();
            $table->timestamps();

            $table->index(['status_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
