<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceLog extends Model
{
    protected $fillable = [
        'employee_id',
        'st_id',
        'reader_id',
        'type',
        'scanned_at',
        'is_auto_closed',
        'is_late',
        'employee_shift_id',
        'shift_mismatch',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'is_auto_closed' => 'boolean',
        'is_late' => 'boolean',
        'shift_mismatch' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(TsHrisEmployee::class, 'employee_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(TsStore::class, 'st_id');
    }

    public function employeeShift(): BelongsTo
    {
        return $this->belongsTo(
            TsHrisEmployeeShift::class,
            'employee_shift_id'
        );
    }

    public function reviewFlags(): HasMany
    {
        return $this->hasMany(AttendanceReviewFlag::class);
    }
}