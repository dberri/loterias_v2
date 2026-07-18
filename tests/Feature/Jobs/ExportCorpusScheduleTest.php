<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExportCorpus;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * INFRA-12 — spec AC P3.1: "WHEN a night passes THEN a scheduled export SHALL
 * write draws and pages to object storage."
 *
 * A job that is never scheduled is a backup that never runs, so the schedule
 * entry itself is part of the requirement rather than deployment trivia.
 */
class ExportCorpusScheduleTest extends TestCase
{
    /**
     * The spec says "nightly, off-peak" and deliberately does not fix a clock
     * time, so this asserts those two properties rather than a specific
     * expression. Pinning an exact string would fail the moment someone shifted
     * the run by ten minutes — a change the requirement permits — which trains
     * people to edit the test to match the code instead of the other way round.
     */
    public function test_the_corpus_export_is_scheduled_nightly_off_peak(): void
    {
        $event = $this->exportEvent();

        $this->assertNotNull($event, 'ExportCorpus is not registered with the scheduler.');

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = explode(' ', $event->expression);

        $this->assertSame(['*', '*', '*'], [$dayOfMonth, $month, $dayOfWeek], 'The export must run every night.');
        $this->assertNotSame('*', $hour, 'An hourly export is not a nightly one.');
        $this->assertNotSame('*', $minute);
        $this->assertContains(
            (int) $hour,
            range(0, 5),
            'The export must run off-peak; it competes with the site for the database.',
        );
    }

    public function test_the_scheduled_export_cannot_overlap_itself(): void
    {
        $event = $this->exportEvent();

        $this->assertNotNull($event);
        $this->assertTrue(
            $event->withoutOverlapping,
            'A slow export could stack on top of the previous night\'s run.',
        );
    }

    private function exportEvent(): ?Event
    {
        return collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains(
                (string) $event->getSummaryForDisplay(),
                ExportCorpus::class,
            ));
    }
}
