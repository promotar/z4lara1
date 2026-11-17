<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiGatewayClient
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function chatGeneral(array $payload): array
    {
        return $this->post('/v1/general/chat', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function chatCoding(array $payload): array
    {
        return $this->post('/v1/coding/chat', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function classifyIntent(array $payload): array
    {
        return $this->post('/v1/router/intent', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function generateImage(array $payload): array
    {
        $payload['wait'] = false;

        return $this->post('/v1/images/generate', $payload, 20);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function generateFastImage(array $payload): array
    {
        $payload['wait'] = false;

        return $this->post('/v1/images/fast-generate', $payload, 20);
    }

    /**
     * @return array<string, mixed>
     */
    public function imageJobStatus(string $jobId): array
    {
        return $this->get('/v1/images/jobs/'.rawurlencode($jobId), 20);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function analyzeVision(array $payload): array
    {
        return $this->post('/v1/vision/analyze', $payload, (int) config('ai.image_timeout', 300));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function searchArtwork(array $payload): array
    {
        return $this->post('/v1/artwork/search', $payload, (int) config('ai.image_timeout', 300));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function ragSearch(array $payload): array
    {
        return $this->post('/v1/rag/search', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $payload, ?int $timeout = null): array
    {
        $baseUrl = rtrim((string) config('ai.gateway_base_url'), '/');
        $apiKey = (string) config('ai.gateway_api_key');

        if ($baseUrl === '') {
            throw new RuntimeException('AI Gateway base URL is not configured.');
        }

        $response = Http::timeout($timeout ?? (int) config('ai.default_timeout', 60))
            ->retry(2, 250)
            ->acceptJson()
            ->when($apiKey !== '', fn ($request) => $request->withHeaders(['X-AI-API-KEY' => $apiKey]))
            ->post($baseUrl.$endpoint, $payload);

        return $this->validatedJson($response, $endpoint);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $endpoint, ?int $timeout = null): array
    {
        $baseUrl = rtrim((string) config('ai.gateway_base_url'), '/');
        $apiKey = (string) config('ai.gateway_api_key');

        if ($baseUrl === '') {
            throw new RuntimeException('AI Gateway base URL is not configured.');
        }

        $response = Http::timeout($timeout ?? (int) config('ai.default_timeout', 60))
            ->retry(2, 250)
            ->acceptJson()
            ->when($apiKey !== '', fn ($request) => $request->withHeaders(['X-AI-API-KEY' => $apiKey]))
            ->get($baseUrl.$endpoint);

        return $this->validatedJson($response, $endpoint);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedJson(Response $response, string $endpoint): array
    {
        if (! $response->successful()) {
            throw new RuntimeException('AI Gateway request failed for '.$endpoint.' with HTTP '.$response->status().'.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('AI Gateway returned an invalid JSON response for '.$endpoint.'.');
        }

        return $data;
    }
}
