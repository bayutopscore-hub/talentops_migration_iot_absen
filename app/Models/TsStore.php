<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TsStore extends Model
{
    protected $table = 'ts_stores';

    public function employees(): HasMany
    {
        return $this->hasMany(TsHrisEmployee::class, 'st_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'st_id');
    }
}
