<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnrecognizedScan extends Model
{
    protected $fillable = [
        'epc',
        'st_id',
        'reader_id',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(TsStore::class, 'st_id');
    }
}
