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
    public function test_the_corpus_export_is_scheduled_nightly_off_peak(): void
    {
        $event = $this->exportEvent();

        $this->assertNotNull($event, 'ExportCorpus is not registered with the scheduler.');
        $this->assertSame('30 3 * * *', $event->expression);
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
