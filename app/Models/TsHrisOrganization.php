<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TsHrisOrganization extends Model
{
    protected $table = 'ts_hris_organizations';

    public function employees(): HasMany
    {
        return $this->hasMany(TsHrisEmployee::class, 'org_id');
    }
}
