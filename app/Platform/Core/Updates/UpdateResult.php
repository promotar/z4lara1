<?php

namespace App\Platform\Core\Updates;

class UpdateResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly bool $successful,
        public readonly string $type,
        public readonly string $slug,
        public readonly ?string $fromVersion,
        public readonly ?string $toVersion,
        public readonly string $step,
        public readonly string $message,
        public readonly ?string $checkpointPath = null,
        public readonly ?string $logPath = null,
        public readonly array $metadata = [],
    ) {
        //
    }

    public static function success(string $type, string $slug, ?string $fromVersion, ?string $toVersion, string $step, string $message, ?string $checkpointPath = null): self
    {
        return new self(true, $type, $slug, $fromVersion, $toVersion, $step, $message, $checkpointPath);
    }

    public static function failure(string $type, string $slug, ?string $fromVersion, ?string $toVersion, string $step, string $message, ?string $checkpointPath = null, ?string $logPath = null): self
    {
        return new self(false, $type, $slug, $fromVersion, $toVersion, $step, $message, $checkpointPath, $logPath);
    }

    public static function noUpdate(string $type, string $slug, string $currentVersion): self
    {
        return new self(true, $type, $slug, $currentVersion, $currentVersion, 'version_check', 'No update is available.');
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->successful,
            $this->type,
            $this->slug,
            $this->fromVersion,
            $this->toVersion,
            $this->step,
            $this->message,
            $this->checkpointPath,
            $this->logPath,
            $metadata,
        );
    }

    public function withLogPath(string $logPath): self
    {
        return new self(
            $this->successful,
            $this->type,
            $this->slug,
            $this->fromVersion,
            $this->toVersion,
            $this->step,
            $this->message,
            $this->checkpointPath,
            $logPath,
            $this->metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'type' => $this->type,
            'slug' => $this->slug,
            'from_version' => $this->fromVersion,
            'to_version' => $this->toVersion,
            'step' => $this->step,
            'message' => $this->message,
            'checkpoint_path' => $this->checkpointPath,
            'log_path' => $this->logPath,
            'metadata' => $this->metadata,
        ];
    }
}
