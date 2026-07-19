<?php

use App\Enums\PageStatus;
use App\Models\Page;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('page factory exposes all status states', function () {
    $cases = [
        'generating' => PageStatus::Generating,
        'generated' => PageStatus::Generated,
        'published' => PageStatus::Published,
        'failed' => PageStatus::Failed,
    ];

    foreach ($cases as $state => $expected) {
        $page = Page::factory()->{$state}()->make();

        expect($page->status)->toBe($expected);
    }
});
