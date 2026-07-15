<?php

namespace App\Models;

use App\Enums\GamesEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Draw extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'draw_number',
        'draw_date',
        'raw_data',
    ];

    protected function casts()
    {
        return [
            'raw_data' => 'array',
            'draw_date' => 'datetime',
            'type' => GamesEnum::class,
        ];
    }

    public function page(): HasOne
    {
        return $this->hasOne(Page::class);
    }

    public function scopeWithoutPage($query)
    {
        return $query->whereDoesntHave('page');
    }

    // Accessor methods for easier data access
    public function getGameNameAttribute(): string
    {
        return match ($this->type) {
            GamesEnum::MEGA_SENA => 'Mega Sena',
            GamesEnum::LOTOFACIL => 'Lotofácil',
            GamesEnum::QUINA => 'Quina',
            default => $this->type->value,
        };
    }

    public function getDrawnNumbersAttribute(): array
    {
        $numbers = $this->raw_data['listaDezenas'] ?? [];

        return array_map(
            fn (mixed $number): string => str_pad((string) (int) $number, 2, '0', STR_PAD_LEFT),
            $numbers,
        );
    }

    public function getIsAccumulatedAttribute(): bool
    {
        return $this->raw_data['acumulado'] ?? false;
    }

    public function getMainPrizeAttribute(): ?float
    {
        $rateio = $this->raw_data['listaRateioPremio'] ?? [];
        if (is_array($rateio) && count($rateio) > 0) {
            $faixaUm = collect($rateio)->where('faixa', 1)->first();

            return $faixaUm['valorPremio'] ?? null;
        }

        return null;
    }

    public function getMainPrizeWinnersAttribute(): ?int
    {
        $rateio = $this->raw_data['listaRateioPremio'] ?? [];
        if (is_array($rateio) && count($rateio) > 0) {
            $faixaUm = collect($rateio)->where('faixa', 1)->first();

            return $faixaUm['numeroDeGanhadores'] ?? null;
        }

        return null;
    }

    public function getFormattedMainPrizeAttribute(): string
    {
        $value = $this->main_prize;

        return $value ? 'R$ '.number_format($value, 2, ',', '.') : 'N/A';
    }

    public function getLocationAttribute(): ?string
    {
        return $this->raw_data['nomeMunicipioUFSorteio'] ?? $this->raw_data['localSorteio'] ?? null;
    }

    public function getNextDrawDateAttribute(): ?string
    {
        $date = $this->raw_data['dataProximoConcurso'] ?? null;

        return filled($date) ? $date : null;
    }

    public function getNextDrawNumberAttribute(): ?int
    {
        return $this->raw_data['numeroConcursoProximo'] ?? null;
    }

    public function getPrevDrawNumberAttribute(): ?int
    {
        return $this->raw_data['numeroConcursoAnterior'] ?? null;
    }

    public function getNextDrawEstimateAttribute(): ?float
    {
        $estimate = $this->raw_data['valorEstimadoProximoConcurso'] ?? null;

        return is_numeric($estimate) ? (float) $estimate : null;
    }
}
