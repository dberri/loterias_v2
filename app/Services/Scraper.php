<?php

namespace App\Services;

use App\Enums\GamesEnum;
use App\Models\Draw;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Scraper
{
    protected string $endpoint = 'https://servicebus2.caixa.gov.br/portaldeloterias/api/';

    public function __construct(
        protected GamesEnum $enum,
        protected ?int $drawNumber
    ) {
        //
    }

    private function getLatestDraw(GamesEnum $enum): ?Draw
    {
        return Draw::where('type', $enum->value)->orderBy('draw_number', 'desc')->first();
    }

    public function scrape(): void
    {
        $number = $this->drawNumber ?: $this->getLatestDraw($this->enum)?->draw_number + 1;

        $url = $this->endpoint . $this->enum->value . '/' . $number;

        $response = Http::withHeaders($this->getHeaders())->get($url);

        if ($response->failed()) {
            Log::error('Failed to scrape draw', [
                'game' => $this->enum->value,
                'draw_number' => $number,
                'response' => $response->json(),
            ]);
            return;
        }

        $draw = new Draw();
        $draw->type = $this->enum->value;
        $draw->draw_number = $number;
        $draw->raw_data = $response->json();
        $draw->save();
    }

    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json, text/plain, */*',
            'Accept' => 'application/json',
            'User-Agent' => $this->getRandomUserAgent(),
        ];
    }

    private function getRandomUserAgent(): string
    {
        $userAgents = [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:97.0) Gecko/20100101 Firefox/97.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.80 Safari/537.36 Edg/98.0.1108.62',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.80 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36',
            'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:97.0) Gecko/20100101 Firefox/97.0',
        ];

        return $userAgents[array_rand($userAgents)];
    }
}
