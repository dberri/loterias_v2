<?php

namespace Database\Factories;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'title' => 'Resultado '.Str::upper(Str::random(6)),
            'slug' => 'resultado/'.Str::lower(Str::random(10)),
            'layout' => 'default',
            'blocks' => [],
            'status' => PageStatus::Generating->value,
            'generated_at' => null,
            'draw_id' => null,
        ];
    }

    public function generating(): static
    {
        return $this->state(fn () => ['status' => PageStatus::Generating->value]);
    }

    public function generated(): static
    {
        return $this->state(fn () => ['status' => PageStatus::Generated->value]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PageStatus::Published->value]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => PageStatus::Failed->value]);
    }
}
