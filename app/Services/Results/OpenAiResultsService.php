<?php

namespace App\Services\Results;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpenAiResultsService extends AbstractAiResultsService
{
    private const API_BASE = 'https://api.openai.com/v1';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        ResultsResponseParser $parser,
    ) {
        parent::__construct($parser);
    }

    /** @throws RuntimeException */
    protected function call(string $prompt): string
    {
        $response = Http::withToken($this->apiKey)->post(self::API_BASE.'/responses', [
            'model' => $this->model,
            'tools' => [['type' => 'web_search_preview']],
            'input' => $prompt,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("OpenAI API request failed with status {$response->status()}: {$response->body()}");
        }

        foreach ($response->json()['output'] ?? [] as $output) {
            if (($output['type'] ?? '') === 'message') {
                return (string) data_get($output, 'content.0.text', '');
            }
        }

        return '';
    }
}
