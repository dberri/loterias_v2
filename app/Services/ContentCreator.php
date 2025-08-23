<?php

namespace App\Services;

use App\Enums\GamesEnum;
use App\Jobs\CheckCompletionBatch;
use App\Models\Draw;
use App\Models\DrawPage;
use Illuminate\Support\Collection;
use NumberFormatter;
use OpenAI\Laravel\Facades\OpenAI;

class ContentCreator
{
    private const FILE_PATH = 'app/private/commands.jsonl';

    public function createContent(Draw $draw)
    {
        $prompt = $this->getPrompt($draw);

        $requestBody = $this->getRequestBody($prompt);

        $result = OpenAI::chat()->create($requestBody);

        $content = $result->choices[0]->message->content;

        $page = $draw->page()->create([
            'title' => 'Pending',
            'content' => $content,
            'url' => '/'.$draw->type->value.'/'.$draw->draw_number,
        ]);

        return $page;
    }

    public function createContentForDraws(Collection $draws)
    {
        $prompts = [];
        foreach ($draws as $draw) {
            $prompts[] = [
                'draw_number' => $draw->draw_number,
                'type' => $draw->type->value,
                'prompt' => $this->getPrompt($draw),
            ];
        }

        $this->createBatchFile($prompts);
        $fileId = $this->uploadBatchFile();
        $batch = $this->startBatch($fileId);

        $draws->each(function ($draw) use ($batch) {
            $draw->page()->create([
                'title' => 'Pending',
                'content' => 'Pending',
                'url' => '/'.$draw->type->value.'/'.$draw->draw_number,
                'batch_id' => $batch['id'],
            ]);
        });

        $this->listenForCompletion($batch);
    }

    /**
     * The file can contain up to 50,000 requests, and can be up to 100 MB in size.
     */
    public function createBatchFile(array $prompts): array
    {
        $file = fopen(storage_path(self::FILE_PATH), 'w');

        foreach ($prompts as $prompt) {
            $customId = 'prompt_'.$prompt['type'].'_'.$prompt['draw_number'];
            $data = [
                'custom_id' => $customId,
                'method' => 'POST',
                'url' => '/v1/chat/completions',
                'body' => $this->getRequestBody($prompt['prompt']),
            ];
            fwrite($file, json_encode($data)."\n");
        }

        fclose($file);

        return $data;
    }

    private function uploadBatchFile()
    {
        $fileResponse = OpenAI::files()->upload([
            'purpose' => 'batch',
            'file' => fopen(storage_path(self::FILE_PATH), 'r'),
        ]);

        return $fileResponse->id;
    }

    private function startBatch(string $fileId)
    {
        $response = OpenAI::batches()->create([
            'input_file_id' => $fileId,
            'endpoint' => '/v1/chat/completions',
            'completion_window' => '24h',
        ]);

        // $response->id; // 'batch_abc123'
        // $response->object; // 'batch'
        // $response->endpoint; // /v1/chat/completions
        // $response->errors; // null
        // $response->completionWindow; // '24h'
        // $response->status; // 'validating'
        // $response->outputFileId; // null
        // $response->errorFileId; // null
        // $response->createdAt; // 1714508499
        // $response->inProgressAt; // null
        // $response->expiresAt; // 1714536634
        // $response->completedAt; // null
        // $response->failedAt; // null
        // $response->expiredAt; // null
        // $response->requestCounts; // null
        // $response->metadata; // ['name' => 'My batch name']

        return $response->toArray(); // ['id' => 'batch_abc123', ...]
    }

    private function listenForCompletion(array $batch)
    {
        CheckCompletionBatch::dispatch($batch['id'])
            ->delay(now()->addMinutes(10));
    }

    public function retrieveBatch(string $batchId): array
    {
        $response = OpenAI::batches()->retrieve($batchId);

        return $response->toArray();
    }

    public function downloadOutputFile(string $batchId, string $fileId)
    {
        $response = OpenAI::files()->download($fileId);

        $file = fopen(storage_path("app/private/$batchId.jsonl"), 'w');
        fwrite($file, $response);
        fclose($file);
    }

    public function updatePagesContent(string $batchId)
    {
        $file = fopen(storage_path("app/private/$batchId.jsonl"), 'r');
        while (! feof($file)) {
            $line = fgets($file);
            $data = json_decode($line, true);
            if (! $data) {
                continue;
            }

            $customId = $data['custom_id'];
            $type = explode('_', $customId)[1]; // mega-sena
            $drawNumber = (int) explode('_', $customId)[2]; // 2345

            if (! $type || ! $drawNumber) {
                // @todo log error
                continue;
            }

            $content = $data['response']['body']['choices'][0]['message']['content'];

            $draw = Draw::where('type', $type)
                ->where('draw_number', $drawNumber)
                ->first();

            if (! $draw) {
                // @todo log error
                continue;
            }

            $drawPage = DrawPage::where('batch_id', $batchId)
                ->where('draw_id', $draw->id)
                ->first();

            if (! $drawPage) {
                // @todo log error
                continue;
            }

            $drawPage->content = $content;
            $drawPage->save();
        }

        fclose($file);
        // unlink(storage_path('app/private/output.jsonl'));
    }

    private function getRequestBody(string $prompt, string $model = 'gpt-4o-mini')
    {
        return [
            'model' => $model,
            'temperature' => rand(20, 40) / 100,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an online journalist writing a news article about the latest lottery draw. All of your responses should be in Brazilian Portuguese. No Exceptions. You should always write articles with a focus on SEO and user engagement. If the lottery draw includes a jackpot winner, you should use a congratulatory tone. If there are no jackpot winners, you should use a tone of anticipation for the next draw. You should not create a title for the article. Only the body of the article.',
                ],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
    }

    private function replacePlaceholders(string $prompt, Draw $draw): string
    {
        $data = $draw->raw_data;

        $winners = array_map(function ($item) {
            return $item['municipio'].' - '.$item['uf'].': '.$item['ganhadores'].' ganhadores';
        }, $data['listaMunicipioUFGanhadores']);

        $placeholders = [
            '{DRAW}' => $draw->draw_number,
            '{DATE}' => $data['dataApuracao'],
            '{ACUMULADO}' => $data['acumulado'] ? 'sim' : 'não',
            '{NEXT_DRAW_DATE}' => $data['dataProximoConcurso'],
            '{PLACE}' => $data['nomeMunicipioUFSorteio'],
            '{LISTA_DEZENAS}' => implode(', ', $data['listaDezenas']),
            '{NEXT_PRIZE}' => $this->formatMoney($data['valorEstimadoProximoConcurso']),
            '{WINNERS_FAIXA_1}' => data_get($data, 'listaRateioPremio.0.numeroDeGanhadores', ''),
            '{PRIZE_FAIXA_1}' => $this->formatMoney(data_get($data, 'listaRateioPremio.0.valorPremio', 0)),
            '{WINNERS_LOCATIONS}' => implode(';', $winners),
            '{WINNERS_FAIXA_2}' => data_get($data, 'listaRateioPremio.1.numeroDeGanhadores', ''),
            '{PRIZE_FAIXA_2}' => $this->formatMoney(data_get($data, 'listaRateioPremio.1.valorPremio', 0)),
            '{WINNERS_FAIXA_3}' => data_get($data, 'listaRateioPremio.2.numeroDeGanhadores', ''),
            '{PRIZE_FAIXA_3}' => $this->formatMoney(data_get($data, 'listaRateioPremio.2.valorPremio', 0)),
            '{WINNERS_FAIXA_4}' => data_get($data, 'listaRateioPremio.3.numeroDeGanhadores', ''),
            '{PRIZE_FAIXA_4}' => $this->formatMoney(data_get($data, 'listaRateioPremio.3.valorPremio', 0)),
            '{WINNERS_FAIXA_5}' => data_get($data, 'listaRateioPremio.4.numeroDeGanhadores', ''),
            '{PRIZE_FAIXA_5}' => $this->formatMoney(data_get($data, 'listaRateioPremio.4.valorPremio', 0)),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $prompt);
    }

    private function formatMoney(int $value): string
    {
        $fmt = numfmt_create('pt_BR', NumberFormatter::CURRENCY);

        return numfmt_format_currency($fmt, $value, 'BRL');
    }

    private function getPrompt(Draw $draw): string
    {
        $prompts = [
            GamesEnum::MEGA_SENA->value => "Crie quatro parágrafos jornalísticos com os seguintes dados sobre o último concurso da loteria 'Mega-Sena'.Sorteio: {DRAW};Data do sorteio: {DATE};Prêmio acumulado: {ACUMULADO};Data próximo sorteio: {NEXT_DRAW_DATE};Local do sorteio: {PLACE};Números sorteados: {LISTA_DEZENAS};Estimativa próximo sorteio: {NEXT_PRIZE};Número de ganhadores com 6 acertos: {WINNERS_FAIXA_1};Rateio 6 acertos: {PRIZE_FAIXA_1};Cidades dos vencedores:{WINNERS_LOCATIONS};Número de ganhadores com 5 acertos (quina): {WINNERS_FAIXA_2};Rateio 5 acertos: {PRIZE_FAIXA_2};Número de ganhadores com 4 acertos: {WINNERS_FAIXA_3};Rateio 4 acertos: {PRIZE_FAIXA_3}",
            GamesEnum::QUINA->value => "Crie quatro parágrafos jornalísticos com os seguintes dados sobre o último concurso da loteria 'Quina'.Sorteio: {DRAW};Data do sorteio: {DATE};Prêmio acumulado: {ACUMULADO};Data próximo sorteio: {NEXT_DRAW_DATE};Local do sorteio: {PLACE};Números sorteados: {LISTA_DEZENAS};Estimativa próximo sorteio: {NEXT_PRIZE};Número de ganhadores com 5 acertos: {WINNERS_FAIXA_1};Rateio 5 acertos: {PRIZE_FAIXA_1};Cidades dos vencedores:{WINNERS_LOCATIONS};Número de ganhadores com 4 acertos: {WINNERS_FAIXA_2};Rateio 4 acertos: {PRIZE_FAIXA_2};Número de ganhadores com 3 acertos: {WINNERS_FAIXA_3};Rateio 3 acertos: {PRIZE_FAIXA_3};Número de ganhadores com 2 acertos: {WINNERS_FAIXA_4};Rateio 2 acertos: {PRIZE_FAIXA_4}",
            GamesEnum::LOTOFACIL->value => "Crie quatro parágrafos jornalísticos com os seguintes dados sobre o último concurso da loteria 'Lotofácil'.Sorteio: {DRAW};Data do sorteio: {DATE};Prêmio acumulado: {ACUMULADO};Data próximo sorteio: {NEXT_DRAW_DATE};Local do sorteio: {PLACE};Números sorteados: {LISTA_DEZENAS};Estimativa próximo sorteio: {NEXT_PRIZE};Número de ganhadores com 15 acertos: {WINNERS_FAIXA_1};Rateio 15 acertos: {PRIZE_FAIXA_1};Cidades dos vencedores:{WINNERS_LOCATIONS};Número de ganhadores com 14 acertos (quina): {WINNERS_FAIXA_2};Rateio 14 acertos: {PRIZE_FAIXA_2};Número de ganhadores com 13 acertos: {WINNERS_FAIXA_3};Rateio 13 acertos: {PRIZE_FAIXA_3};Número de ganhadores com 12 acertos: {WINNERS_FAIXA_4};Rateio 12 acertos: {PRIZE_FAIXA_4};Número de ganhadores com 11 acertos: {WINNERS_FAIXA_5};Rateio 11 acertos: {PRIZE_FAIXA_5}",
        ];

        return $this->replacePlaceholders($prompts[$draw->type->value], $draw);
    }

    // @todo could use prompt to create the most catchy title
    private function generateTitle(): string
    {
        $noWinners = [
            'Mega-Sena: concurso acumula e prêmio sobe para {PRIZE_FAIXA_1}',
            'Mega-Sena: ninguem acerta as 6 dezenas e prêmio acumula para {PRIZE_FAIXA_1}',
            'Mega-Sena: nenhum apostador ganha o concurso {DRAW} e prêmio acumula para {PRIZE_FAIXA_1}',
            'Mega-Sena: nenhuma aposta acerta 6 dezenas e prêmio acumula para {PRIZE_FAIXA_1}',
            'Nenhum apostador ganha o concurso {DRAW} da Mega-Sena e prêmio acumula para {PRIZE_FAIXA_1}',
        ];

        $oneWinner = [
            'Mega-Sena: sortudo acerta 6 dezenas do concurso {DRAW} e vai receber prêmio de {PRIZE_FAIXA_1}',
            'Mega-Sena: uma aposta ganha sozinha o prêmio de {PRIZE_FAIXA_1}',
            'Mega-Sena: um apostador ganha sozinho o prêmio de {PRIZE_FAIXA_1}',
            'Sortudo acerta 6 dezenas do concurso {DRAW} da Mega-Sena e leva o prêmio de {PRIZE_FAIXA_1}',
            'Sortudo ganha concurso {DRAW} da Mega-Sena e leva o prêmio de {PRIZE_FAIXA_1} sozinho',
            'Apostador ganha concurso {DRAW} da Mega-Sena e receberá o prêmio de {PRIZE_FAIXA_1} sozinho',
        ];

        $multipleWinners = [
            'Mega-Sena: {NUMBER_OF_WINNERS} apostas vão dividir prêmio de {PRIZE_FAIXA_1}',
            'Mega-Sena: {NUMBER_OF_WINNERS} apostas vão dividir prêmio de {PRIZE_FAIXA_1}',
            'Mega-Sena: prêmio de {PRIZE_FAIXA_1} será dividido entre {NUMBER_OF_WINNERS} ganhadores',
            'Mega-Sena: {NUMBER_OF_WINNERS} apostadores ganham o prêmio de {PRIZE_FAIXA_1}',
            '{NUMBER_OF_WINNERS} apostadores dividem o prêmio de {PRIZE_FAIXA_1} da Mega-Sena',
        ];

        return '';
    }

    // @todo could use prompt to create the most SEO friendly meta tags
    private function metaTagsGenerator(): array
    {
        return [];
    }
}
