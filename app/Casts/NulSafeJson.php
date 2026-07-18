<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * JSON cast that strips NUL bytes on write.
 *
 * PostgreSQL's json/jsonb types reject \0, and 415 of the 2,608 real Caixa
 * Mega-Sena payloads carry one as junk padding inside nomeTimeCoracaoMesSorte.
 * Stripping happens at the storage boundary only (AD-012); the read path is
 * identical to Laravel's built-in 'array' cast.
 *
 * @implements CastsAttributes<array<mixed>|null, array<mixed>|null>
 */
class NulSafeJson implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        return json_decode($value, true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        return [$key => json_encode($this->stripNulBytes($value))];
    }

    /**
     * Recursively remove NUL bytes from array keys, array values, and strings.
     */
    private function stripNulBytes(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace("\0", '', $value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $stripped = [];

        foreach ($value as $key => $item) {
            $cleanKey = is_string($key) ? str_replace("\0", '', $key) : $key;
            $stripped[$cleanKey] = $this->stripNulBytes($item);
        }

        return $stripped;
    }
}
