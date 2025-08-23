<?php

use App\Models\PillarPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

// Route for pillar pages by game enum value
Route::get('/{game}', function (string $game) {
    $page = PillarPage::where('game', $game)
        ->where('is_published', true)
        ->firstOrFail();
    
    return view("components.filament-fabricator.layouts.{$page->layout}", [
        'page' => $page
    ]);
})->where('game', 'megasena|lotofacil|quina');

// Route for custom pillar pages by slug
Route::get('/{slug}', function (string $slug) {
    $page = PillarPage::where('slug', $slug)
        ->where('is_published', true)
        ->firstOrFail();
    
    return view("components.filament-fabricator.layouts.{$page->layout}", [
        'page' => $page
    ]);
})->where('slug', '[a-z0-9\-]+');
