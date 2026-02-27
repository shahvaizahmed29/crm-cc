<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->index(
                ['assigned_to', 'status_id', 'parent_lead_id'],
                'leads_assigned_status_parent_idx'
            );
        });

        Schema::table('crm_notifications', function (Blueprint $table): void {
            $table->index(
                ['entity_type', 'entity_id', 'type'],
                'crm_notifications_entity_type_idx'
            );
            $table->index(
                ['target_user_id', 'type'],
                'crm_notifications_target_type_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_assigned_status_parent_idx');
        });

        Schema::table('crm_notifications', function (Blueprint $table): void {
            $table->dropIndex('crm_notifications_entity_type_idx');
            $table->dropIndex('crm_notifications_target_type_idx');
        });
    }
};
