<?php

use App\Jobs\ExportCorpus;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
 * Layer 2 of the backup strategy runs nightly, off-peak (INFRA-12).
 *
 * withoutOverlapping matters more than it looks: the export streams the whole
 * corpus, and as `pages` grows a run could outlast the gap to the next tick.
 * Without the guard, a slow night would stack exports on top of one another and
 * turn a performance problem into a correctness one.
 */
Schedule::job(new ExportCorpus)
    ->dailyAt('03:30')
    ->withoutOverlapping();
