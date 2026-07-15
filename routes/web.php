<?php

use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/{game}/resultado/{concurso}', function (string $game, int $concurso) {
    $gameEnum = GamesEnum::tryFrom($game);

    abort_unless($gameEnum, 404);

    $draw = Draw::query()
        ->where('type', $gameEnum)
        ->where('draw_number', $concurso)
        ->firstOrFail();

    $page = Page::query()
        ->with(['draw'])
        ->where('draw_id', $draw->id)
        ->where('status', PageStatus::Published->value)
        ->firstOrFail();

    return view('components.filament-fabricator.layouts.draw-page', [
        'page' => $page,
    ]);
})->whereNumber('concurso');

// Route for custom pillar pages by slug
Route::get('/{slug}', function (string $slug) {
    $page = Page::where('slug', $slug)->firstOrFail();

    return view("components.filament-fabricator.layouts.{$page->layout}", [
        'page' => $page,
    ]);
})->where('slug', '[a-z0-9\-]+');
