<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TsHrisEmployee extends Model
{
    protected $table = 'ts_hris_employees';

    protected $casts = [
        'emp_national_holiday' => 'boolean',
        'emp_absen_branch_company' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(TsStore::class, 'st_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(TsHrisOrganization::class, 'org_id');
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(TsHrisJobPosition::class, 'jps_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(TsHrisShift::class, 'shift_id');
    }

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(TsHrisEmployeeShift::class, 'emp_id');
    }

    public function rfidCards(): HasMany
    {
        return $this->hasMany(RfidCard::class, 'employee_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'employee_id');
    }

    public function attendanceReviewFlags(): HasMany
    {
        return $this->hasMany(AttendanceReviewFlag::class, 'employee_id');
    }
}