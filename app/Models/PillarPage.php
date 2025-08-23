<?php

namespace App\Models;

use App\Enums\GamesEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PillarPage extends Model
{
    protected $fillable = [
        'game',
        'title',
        'slug',
        'layout',
        'content',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'game' => GamesEnum::class,
            'content' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PillarPage $page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
            if (empty($page->layout)) {
                $page->layout = 'pillar-page';
            }
        });

        static::updating(function (PillarPage $page) {
            if ($page->isDirty('title') && empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->game ? "/{$this->game->value}" : "/{$this->slug}",
        );
    }

    // Add a getter for blocks to maintain compatibility with Filament Fabricator blade templates
    protected function blocks(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->content,
        );
    }
}
