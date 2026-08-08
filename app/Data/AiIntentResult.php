<?php

namespace App\Data;

use App\Enums\AiIntent;

class AiIntentResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly AiIntent $intent,
        public readonly float $confidence = 1.0,
        public readonly bool $requiresConfirmation = false,
        public readonly ?string $reason = null,
        public readonly ?string $message = null,
        public readonly array $data = [],
        public readonly ?string $tool = null,
    ) {
        //
    }

    public static function clarification(?string $reason = null): self
    {
        return new self(
            intent: AiIntent::NeedsClarification,
            confidence: 0.0,
            reason: $reason,
            message: 'هل تريد دردشة عادية، توليد صورة، أم تحليل صورة؟',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent->value,
            'confidence' => $this->confidence,
            'requires_confirmation' => $this->requiresConfirmation,
            'reason' => $this->reason,
            'message' => $this->message,
            'data' => $this->data,
            'tool' => $this->tool,
        ];
    }
}
