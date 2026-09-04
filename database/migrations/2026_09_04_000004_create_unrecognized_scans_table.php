<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unrecognized_scans', function (Blueprint $table) {
            $table->id();
            $table->string('epc');
            $table->foreignId('st_id')
                ->constrained('ts_stores')
                ->cascadeOnDelete();
            $table->string('reader_id')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();
            $table->index(['epc', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unrecognized_scans');
    }
};
