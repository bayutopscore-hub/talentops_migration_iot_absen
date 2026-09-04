<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('attendance:auto-close')
    ->dailyAt('23:59')
    ->withoutOverlapping();
