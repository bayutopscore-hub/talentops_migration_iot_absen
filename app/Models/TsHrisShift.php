<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TsHrisShift extends Model
{
    protected $table = 'ts_hris_shifts';

    public function employees(): HasMany
    {
        return $this->hasMany(TsHrisEmployee::class, 'shift_id');
    }

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(TsHrisEmployeeShift::class, 'shift_id');
    }
}
