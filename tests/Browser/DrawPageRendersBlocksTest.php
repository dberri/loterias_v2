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

// Response status is asserted explicitly (not inferred from an error page's
// body) per spec.md's edge case: "the seeded page's status is not Published
// THEN the public route 404s — the test SHALL assert 200 explicitly rather
// than asserting on an error page's body." pest-plugin-browser has no
// assertStatus() helper (it drives a real browser, not an HTTP client), so
// the status is read via the browser's own Navigation Timing API, which
// Chromium exposes synchronously and reflects the real HTTP response.
const ASSERT_OK_SCRIPT = 'performance.getEntriesByType("navigation")[0].responseStatus';

// Each block gets its own test — asserted individually rather than in one bulk
// assertion — so a failure names exactly which block stopped rendering
// (PEST-08 AC2), instead of just reporting "something is missing."

test('hero-section renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))
        ->assertScript(ASSERT_OK_SCRIPT, 200)
        ->assertSee($fixture['markers']['hero-section']);
});

test('results-grid renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))
        ->assertScript(ASSERT_OK_SCRIPT, 200)
        ->assertSee($fixture['markers']['results-grid']);
});

test('individual-draw-details renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))
        ->assertScript(ASSERT_OK_SCRIPT, 200)
        ->assertSee($fixture['markers']['individual-draw-details']);
});

test('faq renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))
        ->assertScript(ASSERT_OK_SCRIPT, 200)
        ->assertSee($fixture['markers']['faq']);
});

test('related-links renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))
        ->assertScript(ASSERT_OK_SCRIPT, 200)
        ->assertSee($fixture['markers']['related-links']);
});

test('rich-text-content renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))
        ->assertScript(ASSERT_OK_SCRIPT, 200)
        ->assertSee($fixture['markers']['rich-text-content']);
});

test('statistics-cards renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))
        ->assertScript(ASSERT_OK_SCRIPT, 200)
        ->assertSee($fixture['markers']['statistics-cards']);
});

test('latest-results renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))
        ->assertScript(ASSERT_OK_SCRIPT, 200)
        ->assertSee($fixture['markers']['latest-results']);
});

test('number-generator renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))
        ->assertScript(ASSERT_OK_SCRIPT, 200)
        ->assertSee($fixture['markers']['number-generator']);
});

test('how-to-play renders its marker', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))
        ->assertScript(ASSERT_OK_SCRIPT, 200)
        ->assertSee($fixture['markers']['how-to-play']);
});

test('the draw page raises no javascript console errors', function () {
    $fixture = fixture();
    visit(publicUrl($fixture))
        ->assertScript(ASSERT_OK_SCRIPT, 200)
        ->assertNoJavaScriptErrors();
});
