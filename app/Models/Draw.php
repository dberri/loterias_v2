<?php

namespace App\Models;

use App\Enums\GamesEnum;
use Illuminate\Database\Eloquent\Model;

class Draw extends Model
{
    protected function casts()
    {
        return [
            'raw_data' => 'array',
            'type' => GamesEnum::class,
        ];
    }
}
