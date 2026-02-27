<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 120)->index();
            $table->string('entity_type', 120)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->string('action_url', 500)->nullable();
            $table->timestamp('notify_at')->index();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('dedupe_key', 190)->nullable()->unique();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['target_user_id', 'read_at', 'notify_at'], 'crm_notifications_user_read_notify_idx');
            $table->index(['entity_type', 'entity_id'], 'crm_notifications_entity_idx');
            $table->index(['status', 'notify_at'], 'crm_notifications_status_notify_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notifications');
    }
};
