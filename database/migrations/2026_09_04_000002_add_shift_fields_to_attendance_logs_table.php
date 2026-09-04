<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('employee_shift_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('ts_hris_employee_shifts')
                ->nullOnDelete();

            $table->boolean('shift_mismatch')
                ->default(false)
                ->after('employee_shift_id');

            $table->index(
                ['employee_id', 'scanned_at'],
                'attendance_logs_employee_scanned_at_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex('attendance_logs_employee_scanned_at_index');
            $table->dropForeign(['employee_shift_id']);
            $table->dropColumn([
                'employee_shift_id',
                'shift_mismatch',
            ]);
        });
    }
};
