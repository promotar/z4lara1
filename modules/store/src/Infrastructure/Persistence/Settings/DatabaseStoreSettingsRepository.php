<?php

namespace Modules\Store\Infrastructure\Persistence\Settings;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\Store\Domain\Settings\StoreSettingsRepository;
use Modules\Store\Domain\Shared\ActorContext;

final readonly class DatabaseStoreSettingsRepository implements StoreSettingsRepository
{
    public function __construct(private ConnectionInterface $database) {}

    public function all(): array
    {
        if (! $this->database->getSchemaBuilder()->hasTable('store_settings')) {
            return [];
        }

        return $this->database->table('store_settings')->pluck('value', 'key')->map(
            static fn (mixed $value): mixed => json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR),
        )->all();
    }

    public function put(array $settings, ActorContext $actor, DateTimeImmutable $now): void
    {
        if (! $this->database->getSchemaBuilder()->hasTable('store_settings')) {
            return;
        }

        foreach ($settings as $key => $value) {
            $old = $this->database->table('store_settings')->where('key', $key)->value('value');
            $this->database->table('store_settings')->updateOrInsert(['key' => $key], [
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($this->database->getSchemaBuilder()->hasTable('store_setting_audits')) {
                $this->database->table('store_setting_audits')->insert([
                    'setting_key' => $key,
                    'old_value' => $old,
                    'new_value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'changed_by' => $actor->type === 'user' ? (int) $actor->id : null,
                    'created_at' => $now,
                ]);
            }
        }
    }
}
