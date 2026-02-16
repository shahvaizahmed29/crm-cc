<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->index(['assigned_to', 'status_id', 'updated_at'], 'leads_assigned_status_updated_idx');
            $table->index(['status_id', 'updated_at'], 'leads_status_updated_idx');
            $table->index(['assigned_to', 'updated_at'], 'leads_assigned_updated_idx');
        });

        Schema::table('lead_notes', function (Blueprint $table): void {
            $table->index(['lead_id', 'created_at'], 'lead_notes_lead_created_idx');
            $table->index(['created_by', 'created_at'], 'lead_notes_creator_created_idx');
        });

        Schema::table('lead_phones', function (Blueprint $table): void {
            $table->index(['lead_id', 'created_at'], 'lead_phones_lead_created_idx');
        });

        Schema::table('lead_emails', function (Blueprint $table): void {
            $table->index(['lead_id', 'created_at'], 'lead_emails_lead_created_idx');
        });

        Schema::table('role_user', function (Blueprint $table): void {
            $table->index('role_id', 'role_user_role_id_idx');
        });

        Schema::table('status_role', function (Blueprint $table): void {
            $table->index('role_id', 'status_role_role_id_idx');
        });

        Schema::table('session_times', function (Blueprint $table): void {
            $table->index(['user_id', 'ended_at'], 'session_times_user_ended_idx');
        });
    }

    public function down(): void
    {
        Schema::table('session_times', function (Blueprint $table): void {
            $table->dropIndex('session_times_user_ended_idx');
        });

        Schema::table('status_role', function (Blueprint $table): void {
            $table->dropIndex('status_role_role_id_idx');
        });

        Schema::table('role_user', function (Blueprint $table): void {
            $table->dropIndex('role_user_role_id_idx');
        });

        Schema::table('lead_emails', function (Blueprint $table): void {
            $table->dropIndex('lead_emails_lead_created_idx');
        });

        Schema::table('lead_phones', function (Blueprint $table): void {
            $table->dropIndex('lead_phones_lead_created_idx');
        });

        Schema::table('lead_notes', function (Blueprint $table): void {
            $table->dropIndex('lead_notes_creator_created_idx');
            $table->dropIndex('lead_notes_lead_created_idx');
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_assigned_updated_idx');
            $table->dropIndex('leads_status_updated_idx');
            $table->dropIndex('leads_assigned_status_updated_idx');
        });
    }
};
