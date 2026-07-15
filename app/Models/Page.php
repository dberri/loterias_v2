<?php

namespace App\Models;

use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Z3d0X\FilamentFabricator\Models\Page as FabricatorPage;

class Page extends FabricatorPage
{
    use HasFactory;

    protected $casts = [
        'blocks' => 'array',
        'parent_id' => 'integer',
        'status' => PageStatus::class,
        'generated_at' => 'datetime',
    ];

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }
}
