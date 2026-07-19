<?php

namespace Tests\Browser\AdminEditsPageTest;

use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Str;

// SPEC_DEVIATION: this test seeds its own minimal (blocks-empty) Published page
// rather than tests/Browser/Fixtures/DrawPageFixture.php. The fixture's full
// 10-block payload makes the Filament page-builder editor render every block's
// complete form schema on load, which is dramatically slower (~3 minutes,
// measured) than editing a page with no blocks and risks flaking element
// waits. This test only needs a title field to edit and a public URL to check,
// neither of which requires any blocks — DrawPageFixture remains the correct,
// necessary fixture for T11's block-rendering test, where the full block set
// is exactly what's under test.

test('an unauthenticated visitor is redirected to login and never sees the page editor', function () {
    visit('/admin/pages')
        ->assertPathIs('/admin/login')
        ->assertDontSee('Add to blocks');
});

test('an admin can edit a page title through the real editor and the public page reflects it', function () {
    $user = User::factory()->create();
    $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA, 2608)->create();

    $originalTitle = 'Resultado Mega-Sena concurso 2608';
    $page = Page::factory()->published()->create([
        'draw_id' => $draw->id,
        'title' => $originalTitle,
        'slug' => 'megasena/resultado/2608',
        'layout' => 'draw-page',
        'blocks' => [],
    ]);

    $newTitle = 'Marker-EditedTitle-'.Str::random(10);
    $publicUrl = '/megasena/resultado/2608';

    // Real /admin/login form — actingAs() would bypass the auth boundary PEST-10 requires.
    visit('/admin/login')
        ->type('[id="form.email"]', $user->email)
        ->type('[id="form.password"]', 'password')
        ->press('button:has-text("Sign in")')
        ->assertPathIs('/admin');

    visit("/admin/pages/{$page->id}/edit")
        ->assertValue('[id="form.title"]', $originalTitle)
        ->fill('[id="form.title"]', $newTitle)
        ->press('Save changes')
        ->assertSee('Saved');

    // SPEC_DEVIATION: the draw-page layout only places the page title inside
    // <title> (see resources/views/components/filament-fabricator/layouts/draw-page.blade.php),
    // never in visible body text, so assertSee()/assertDontSee() (which only
    // match visible text) cannot see it. assertSourceHas()/assertSourceMissing()
    // check the raw rendered HTML, which does cover <title>, and are the
    // faithful reading of PEST-07's "rendered page SHALL contain/SHALL NOT
    // contain" wording. Asserted separately per lesson L-005.
    visit($publicUrl)
        ->assertSourceHas($newTitle)
        ->assertSourceMissing($originalTitle);

    expect($page->fresh()->status)->toBe(PageStatus::Published);
});
