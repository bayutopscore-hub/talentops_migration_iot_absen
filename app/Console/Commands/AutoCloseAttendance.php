<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\AttendanceReviewFlag;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AutoCloseAttendance extends Command
{
    protected $signature = 'attendance:auto-close';

    protected $description = 'Auto-close attendance check-ins without a check-out';

    public function handle(): int
    {
        $now = now();
        $closedCount = 0;
        $skippedCount = 0;

        $candidates = AttendanceLog::query()
            ->where('type', 'check_in')
            ->whereBetween('scanned_at', [
                $now->copy()->subDay()->startOfDay(),
                $now,
            ])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('attendance_logs as checkout')
                    ->whereColumn('checkout.employee_id', 'attendance_logs.employee_id')
                    ->where('checkout.type', 'check_out')
                    ->whereColumn('checkout.scanned_at', '>', 'attendance_logs.scanned_at');
            })
            ->with('employeeShift')
            ->orderByDesc('scanned_at')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($logs) => $logs->first());

        foreach ($candidates as $candidate) {
            $closeAt = $this->closeAtFor($candidate, $now);

            if ($closeAt->isAfter($now)) {
                $skippedCount++;
                continue;
            }

            $closed = DB::transaction(function () use ($candidate, $closeAt) {
                $log = AttendanceLog::query()
                    ->whereKey($candidate->id)
                    ->lockForUpdate()
                    ->first();

                if (!$log || $log->type !== 'check_in') {
                    return false;
                }

                $hasCheckout = AttendanceLog::query()
                    ->where('employee_id', $log->employee_id)
                    ->where('type', 'check_out')
                    ->where('scanned_at', '>', $log->scanned_at)
                    ->exists();

                if ($hasCheckout) {
                    return false;
                }

                $autoClosedLog = AttendanceLog::create([
                    'employee_id' => $log->employee_id,
                    'st_id' => $log->st_id,
                    'type' => 'check_out',
                    'scanned_at' => $closeAt,
                    'is_auto_closed' => true,
                    'is_late' => false,
                    'employee_shift_id' => $log->employee_shift_id,
                    'shift_mismatch' => false,
                ]);

                AttendanceReviewFlag::create([
                    'attendance_log_id' => $autoClosedLog->id,
                    'employee_id' => $log->employee_id,
                    'reason' => 'auto_closed',
                    'note' => "Check-out otomatis dibuat untuk check-in {$log->scanned_at->toDateTimeString()}.",
                ]);

                return true;
            });

            if ($closed) {
                $closedCount++;
            }
        }

        $this->info("Auto-closed {$closedCount} attendance log(s).");

        if ($skippedCount > 0) {
            $this->line("Skipped {$skippedCount} overnight attendance log(s) not yet due.");
        }

        return self::SUCCESS;
    }

    private function closeAtFor(AttendanceLog $log, Carbon $now): Carbon
    {
        $shift = $log->employeeShift;

        if (!$shift) {
            $endOfDay = $log->scanned_at->copy()->endOfDay();

            return $endOfDay->isFuture() ? $now : $endOfDay;
        }

        $startTime = Carbon::parse($shift->empshift_start_time);
        $endTime = Carbon::parse($shift->empshift_end_time);
        $isOvernight = $endTime->lessThanOrEqualTo($startTime);

        if (!$isOvernight) {
            $endOfDay = $log->scanned_at->copy()->endOfDay();

            return $endOfDay->isFuture() ? $now : $endOfDay;
        }

        $shiftEnd = Carbon::parse(
            $log->scanned_at->toDateString() . ' ' . $shift->empshift_end_time
        );

        return $shiftEnd->addDay();
    }
}
