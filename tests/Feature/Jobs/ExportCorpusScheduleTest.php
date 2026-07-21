<?php

namespace Tests\Feature\Jobs\ExportCorpusScheduleTest;

use App\Jobs\ExportCorpus;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('the corpus export is scheduled nightly off peak', function () {
    $event = exportEvent();

    expect($event)->not->toBeNull('ExportCorpus is not registered with the scheduler.');

    [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = explode(' ', $event->expression);

    expect([$dayOfMonth, $month, $dayOfWeek])->toBe(['*', '*', '*'], 'The export must run every night.');
    $this->assertNotSame('*', $hour, 'An hourly export is not a nightly one.');
    $this->assertNotSame('*', $minute);
    expect((int) $hour)->toBeBetween(0, 5, 'The export must run off-peak; it competes with the site for the database.');
});

test('the scheduled export cannot overlap itself', function () {
    $event = exportEvent();

    expect($event)->not->toBeNull();
    expect($event->withoutOverlapping)->toBeTrue('A slow export could stack on top of the previous night\'s run.');
});

function exportEvent(): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains(
            (string) $event->getSummaryForDisplay(),
            ExportCorpus::class,
        ));
}
