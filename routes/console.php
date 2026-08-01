<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('supplier:sync-stock')
    ->everyThirtyMinutes()
    ->withoutOverlapping(25)
    ->onOneServer();

Schedule::command('supplier:discover --pages=5 --limit=100')
    ->weeklyOn(7, '02:15')
    ->withoutOverlapping(120)
    ->onOneServer();

Schedule::command('supplier:catalog-tree')
    ->dailyAt('01:45')
    ->withoutOverlapping(120)
    ->onOneServer();

Schedule::command('supplier:crawl-catalog --nodes=2 --pages=2 --products=100')
    ->everyTenMinutes()
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('supplier:categorize')
    ->dailyAt('03:00')
    ->withoutOverlapping(30)
    ->onOneServer();
