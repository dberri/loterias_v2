<?php

use Illuminate\Support\Facades\Route;
use Z3d0X\FilamentFabricator\Models\Page;

Route::get('/', function () {
    return view('home');
});

// Route for custom pillar pages by slug
Route::get('/{slug}', function (string $slug) {
    $page = Page::where('slug', $slug)->firstOrFail();
    
    return view("components.filament-fabricator.layouts.{$page->layout}", [
        'page' => $page
    ]);
})->where('slug', '[a-z0-9\-]+');
