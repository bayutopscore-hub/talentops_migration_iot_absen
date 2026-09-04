<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TsHrisEmployeeShift extends Model
{
    protected $table = 'ts_hris_employee_shifts';

    protected $fillable = [
        'emp_id',
        'shift_id',
        'empshift_code',
        'empshift_start_date',
        'empshift_end_date',
        'empshift_start_time',
        'empshift_end_time',
        'empshift_active',
        'empshift_delete',
    ];

    protected $casts = [
        'empshift_start_date' => 'date',
        'empshift_end_date' => 'date',
        'empshift_active' => 'boolean',
        'empshift_delete' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(TsHrisEmployee::class, 'emp_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(TsHrisShift::class, 'shift_id');
    }

    public function scopeActiveOn(
        Builder $query,
        string $date
    ): Builder {
        return $query
            ->where('empshift_active', true)
            ->where('empshift_delete', false)
            ->whereDate('empshift_start_date', '<=', $date)
            ->whereDate('empshift_end_date', '>=', $date);
    }
}