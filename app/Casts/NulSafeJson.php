<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * JSON cast that strips NUL bytes on write.
 *
 * 415 of the 2,608 real Caixa Mega-Sena payloads carry a NUL as junk padding
 * inside nomeTimeCoracaoMesSorte.
 *
 * Note the failure this prevents is silent, not loud. raw_data is a `json`
 * column, and PostgreSQL's `json` type ACCEPTS \0 on insert -- only `jsonb`
 * rejects it. Without this cast those rows would insert cleanly and then be
 * permanently unreadable: any ->> extraction raises SQLSTATE 22P05, and a
 * later ALTER TYPE ... jsonb is blocked. Nothing during a cutover would
 * signal the problem.
 *
 * Stripping happens at the storage boundary only (AD-012); the read path is
 * identical to Laravel's built-in 'array' cast. The narrow exception to
 * AD-001's byte-faithfulness rule is licensed ONLY by the accessor-parity
 * tests -- if those are removed, this cast is no longer justified.
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
