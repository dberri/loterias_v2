<?php

namespace Tests\Unit\Factories;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_factory_exposes_all_status_states(): void
    {
        $cases = [
            'generating' => PageStatus::Generating,
            'generated' => PageStatus::Generated,
            'published' => PageStatus::Published,
            'failed' => PageStatus::Failed,
        ];

        foreach ($cases as $state => $expected) {
            $page = Page::factory()->{$state}()->make();

            $this->assertSame($expected, $page->status);
        }
    }
}
