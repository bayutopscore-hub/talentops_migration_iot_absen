<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\AttendanceReviewFlag;
use App\Models\RfidCard;
use App\Models\TsHrisEmployeeShift;
use App\Models\UnrecognizedScan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceScanController extends Controller
{
    protected const DEBOUNCE_SECONDS = 60;
    protected const GRACE_PERIOD_MINUTES = 5;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'epc' => ['required', 'string', 'max:255'],
            'reader_id' => ['nullable', 'string', 'max:100'],
            'st_id' => ['required', 'integer', 'exists:ts_stores,id'],
            'scanned_at' => ['required', 'date', 'before_or_equal:now'],
        ]);

        $card = RfidCard::where('epc', $validated['epc'])
            ->where('status', 'active')
            ->with('employee')
            ->first();

        if (!$card) {
            UnrecognizedScan::create([
                'epc' => $validated['epc'],
                'st_id' => $validated['st_id'],
                'reader_id' => $validated['reader_id'] ?? null,
                'scanned_at' => $validated['scanned_at'],
            ]);

            return response()->json(['status' => 'unrecognized'], 200);
        }

        $employee = $card->employee;

        if (!$employee) {
            return response()->json(['status' => 'invalid_card'], 422);
        }

        if ((int) $employee->st_id !== (int) $validated['st_id']) {
            return response()->json(['status' => 'invalid_store'], 422);
        }

        $scannedAt = Carbon::parse($validated['scanned_at']);

        $result = DB::transaction(function () use ($validated, $employee, $scannedAt) {
            $lastLog = AttendanceLog::where('employee_id', $employee->id)
                ->latest('scanned_at')
                ->lockForUpdate()
                ->first();

            if ($lastLog && $scannedAt->diffInSeconds($lastLog->scanned_at) < self::DEBOUNCE_SECONDS) {
                return [
                    'status' => 'ignored',
                    'reason' => 'debounced',
                ];
            }

            $type = ($lastLog && $lastLog->type === 'check_in'
                && $lastLog->scanned_at->isSameDay($scannedAt))
                ? 'check_out'
                : 'check_in';

            $activeShift = TsHrisEmployeeShift::where('emp_id', $employee->id)
                ->activeOn($scannedAt->toDateString())
                ->first();

            $isLate = false;
            $shiftMismatch = false;

            if ($type === 'check_in') {
                if ($activeShift) {
                    $shiftStart = Carbon::parse(
                        $scannedAt->toDateString() . ' ' . $activeShift->empshift_start_time
                    );
                    $graceDeadline = $shiftStart->copy()->addMinutes(self::GRACE_PERIOD_MINUTES);
                    $isLate = $scannedAt->gt($graceDeadline);
                } else {
                    $shiftMismatch = true;
                }
            }

            $employeeShiftId = $type === 'check_out'
                ? $lastLog?->employee_shift_id
                : $activeShift?->id;

            $log = AttendanceLog::create([
                'employee_id' => $employee->id,
                'st_id' => $validated['st_id'],
                'reader_id' => $validated['reader_id'] ?? null,
                'type' => $type,
                'scanned_at' => $scannedAt,
                'is_late' => $isLate,
                'employee_shift_id' => $employeeShiftId,
                'shift_mismatch' => $shiftMismatch,
            ]);

            if ($shiftMismatch) {
                AttendanceReviewFlag::create([
                    'attendance_log_id' => $log->id,
                    'employee_id' => $employee->id,
                    'reason' => 'shift_mismatch',
                    'note' => "Karyawan absen pada {$scannedAt->toDateTimeString()} tanpa shift aktif terdaftar di ts_hris_employee_shifts.",
                ]);
            }

            return [
                'status' => 'logged',
                'employee_name' => $employee->emp_name,
                'type' => $type,
                'is_late' => $isLate,
                'shift_mismatch' => $shiftMismatch,
            ];
        });

        if ($result['status'] === 'ignored') {
            return response()->json($result, 200);
        }

        return response()->json($result, 201);
    }
}
