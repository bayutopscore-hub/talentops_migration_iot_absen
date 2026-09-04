<?php

use App\Http\Controllers\Api\AttendanceScanController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/attendance/scan',
    [AttendanceScanController::class, 'store']
);
