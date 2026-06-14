<?php

namespace App\Services\Results;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GeminiResultsService extends AbstractAiResultsService
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

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
        $response = Http::post(
            self::API_BASE."/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $prompt]],
                    ],
                ],
                'tools' => [['google_search' => (object) []]],
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException("Gemini API request failed with status {$response->status()}: {$response->body()}");
        }

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }
}
