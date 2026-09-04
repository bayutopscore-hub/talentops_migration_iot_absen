<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TsHrisJobPosition extends Model
{
    protected $table = 'ts_hris_job_positions';

    public function employees(): HasMany
    {
        return $this->hasMany(TsHrisEmployee::class, 'jps_id');
    }
}
