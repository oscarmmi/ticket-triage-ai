<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiLlmClient implements LlmClient
{
    public function complete(array $input, array $schema): LlmResponse
    {
        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $key = (string) config('services.gemini.key');

        $response = Http::timeout(12)
            ->acceptJson()
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => "Classify this support ticket for Harbor Desk. Return only JSON matching the schema. Be concise, do not invent order or account details, and write a calm, helpful reply.\n\nTicket:\n".json_encode($input, JSON_THROW_ON_ERROR),
                    ]],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $this->toGeminiSchema($schema),
                    'temperature' => 0.2,
                ],
            ]);

        $response->throw();
        $content = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        return new LlmResponse($content, $model);
    }

    /** @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function toGeminiSchema(array $schema): array
    {
        $result = [];
        foreach ($schema as $key => $value) {
            if ($key === 'type' && is_string($value)) {
                $result[$key] = strtoupper($value);
            } elseif ($key === 'properties' && is_array($value)) {
                $result[$key] = array_map($this->toGeminiSchema(...), $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
