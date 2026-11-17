<?php

namespace App\Services\Ai;

class AiPromptSanitizer
{
    public function sanitizeMessage(string $message): string
    {
        $message = trim(strip_tags($message));
        $message = preg_replace('/data:[^;]+;base64,[A-Za-z0-9+\/=\\r\\n]+/', '[base64 omitted]', $message) ?? $message;

        return mb_substr($message, 0, 4000);
    }

    /**
     * @param array<int, mixed> $attachments
     * @return array<int, array<string, mixed>>
     */
    public function sanitizeAttachments(array $attachments): array
    {
        return collect($attachments)
            ->filter(fn (mixed $attachment): bool => is_array($attachment))
            ->map(function (array $attachment): array {
                $type = (string) ($attachment['type'] ?? '');
                $mime = (string) ($attachment['mime'] ?? $attachment['mime_type'] ?? '');
                $url = (string) ($attachment['url'] ?? '');
                $name = (string) ($attachment['name'] ?? $attachment['filename'] ?? '');

                return array_filter([
                    'type' => mb_substr($type, 0, 50),
                    'mime' => mb_substr($mime, 0, 100),
                    'url' => $url !== '' && ! str_contains($url, 'base64,') ? mb_substr($url, 0, 1000) : null,
                    'name' => mb_substr($name, 0, 255),
                    'has_inline_data' => isset($attachment['data']) || str_contains($url, 'base64,'),
                ], fn (mixed $value): bool => $value !== null && $value !== '');
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $attachments
     */
    public function hasImage(array $attachments): bool
    {
        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $type = strtolower((string) ($attachment['type'] ?? ''));
            $mime = strtolower((string) ($attachment['mime'] ?? $attachment['mime_type'] ?? ''));

            if ($type === 'image' || str_starts_with($mime, 'image/')) {
                return true;
            }
        }

        return false;
    }
}
