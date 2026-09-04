<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_review_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_log_id')
                ->constrained('attendance_logs')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->constrained('ts_hris_employees')
                ->cascadeOnDelete();
            $table->enum('reason', [
                'shift_mismatch',
                'auto_closed',
                'other',
            ]);
            $table->text('note')->nullable();
            $table->enum('review_status', [
                'pending',
                'reviewed',
                'resolved',
            ])->default('pending');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['review_status', 'reason'],
                'attendance_review_flags_status_reason_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_review_flags');
    }
};
