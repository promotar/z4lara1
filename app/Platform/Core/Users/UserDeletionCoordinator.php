<?php

namespace App\Platform\Core\Users;

use App\Models\User;
use App\Platform\Core\Contracts\UserDeletionParticipant;
use App\Platform\Core\Models\Plugin;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class UserDeletionCoordinator
{
    public function __construct(private readonly Container $container) {}

    /** @return list<string> */
    public function blockers(User $user): array
    {
        $participants = $this->participants();
        $blockers = [];

        foreach ($this->declaredPlugins() as $plugin) {
            $mode = (string) data_get($plugin->manifest, 'integrations.user_deletion.mode', '');

            if ($mode === 'none') {
                continue;
            }

            if ($mode !== 'participant') {
                $blockers[] = "Plugin [{$plugin->slug}] has not declared a user-deletion policy.";

                continue;
            }

            if ($plugin->status !== Plugin::STATUS_ACTIVE) {
                $blockers[] = "Plugin [{$plugin->slug}] is not active, so its user data cannot be inspected safely.";

                continue;
            }

            $participant = $participants[$plugin->slug] ?? null;
            if (! $participant instanceof UserDeletionParticipant) {
                $blockers[] = "Plugin [{$plugin->slug}] did not register its user-deletion participant.";

                continue;
            }

            try {
                foreach ($participant->blockers($user) as $reason) {
                    $reason = trim((string) $reason);
                    if ($reason !== '') {
                        $blockers[] = "{$plugin->name}: {$reason}";
                    }
                }
            } catch (Throwable) {
                $blockers[] = "Plugin [{$plugin->slug}] could not inspect its user data safely.";
            }
        }

        return array_values(array_unique($blockers));
    }

    public function cleanup(User $user): void
    {
        $participants = $this->participants();

        foreach ($this->declaredPlugins() as $plugin) {
            if ($plugin->status !== Plugin::STATUS_ACTIVE
                || data_get($plugin->manifest, 'integrations.user_deletion.mode') !== 'participant') {
                continue;
            }

            $participant = $participants[$plugin->slug] ?? null;
            if (! $participant instanceof UserDeletionParticipant) {
                throw new \RuntimeException("Plugin [{$plugin->slug}] user-deletion participant is unavailable.");
            }

            $participant->cleanup($user);
        }
    }

    /** @return array<string, UserDeletionParticipant> */
    private function participants(): array
    {
        $resolved = [];

        foreach ($this->container->tagged(UserDeletionParticipant::class) as $participant) {
            if (! $participant instanceof UserDeletionParticipant) {
                continue;
            }

            $resolved[$participant->pluginSlug()] = $participant;
        }

        return $resolved;
    }

    /** @return \Illuminate\Support\Collection<int, Plugin> */
    private function declaredPlugins(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('plugins')) {
            return collect();
        }

        return Plugin::query()
            ->orderBy('slug')
            ->get()
            ->filter(fn (Plugin $plugin): bool => ! $plugin->isCore());
    }
}
