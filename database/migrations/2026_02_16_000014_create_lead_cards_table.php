<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('bank_tollfree')->nullable();
            $table->string('card_number')->nullable();
            $table->string('name_on_card')->nullable();
            $table->string('card_expiry')->nullable();
            $table->string('card_cvc')->nullable();
            $table->decimal('balance', 12, 2)->nullable();
            $table->decimal('available_amount', 12, 2)->nullable();
            $table->string('last_payment')->nullable();
            $table->string('due_payment')->nullable();
            $table->decimal('apr', 6, 2)->nullable();
            $table->boolean('charge_card')->default(false);
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
            $table->index('card_number');
            $table->index('bank_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_cards');
    }
};
