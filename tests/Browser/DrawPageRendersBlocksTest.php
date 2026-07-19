<?php

namespace Tests\Browser\DrawPageRendersBlocksTest;

use Tests\Browser\Fixtures\DrawPageFixture;

/**
 * @return array{draw: \App\Models\Draw, page: \App\Models\Page, markers: array<string, string>}
 */
function fixture(): array
{
    return DrawPageFixture::make();
}

function publicUrl(array $fixture): string
{
    return sprintf('/%s/resultado/%d', $fixture['draw']->type->value, $fixture['draw']->draw_number);
}

// Each block gets its own test — asserted individually rather than in one bulk
// assertion — so a failure names exactly which block stopped rendering
// (PEST-08 AC2), instead of just reporting "something is missing."

test('hero-section renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))->assertSee($fixture['markers']['hero-section']);
});

test('results-grid renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))->assertSee($fixture['markers']['results-grid']);
});

test('individual-draw-details renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))->assertSee($fixture['markers']['individual-draw-details']);
});

test('faq renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))->assertSee($fixture['markers']['faq']);
});

test('related-links renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))->assertSee($fixture['markers']['related-links']);
});

test('rich-text-content renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))->assertSee($fixture['markers']['rich-text-content']);
});

test('statistics-cards renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))->assertSee($fixture['markers']['statistics-cards']);
});

test('latest-results renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))->assertSee($fixture['markers']['latest-results']);
});

test('number-generator renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))->assertSee($fixture['markers']['number-generator']);
});

test('how-to-play renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))->assertSee($fixture['markers']['how-to-play']);
});

test('the draw page raises no javascript console errors', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))->assertNoJavaScriptErrors();
});
