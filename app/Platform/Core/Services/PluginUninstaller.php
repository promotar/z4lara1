<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Plugins\Uninstall\PluginUninstallFlow;
use App\Platform\Core\Repositories\PluginRepository;

class PluginUninstaller
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PluginUninstallFlow $flow,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function uninstall(Plugin|string $plugin): array
    {
        return $this->purge($plugin);
    }

    /**
     * @return array<string, mixed>
     */
    public function purge(Plugin|string $plugin): array
    {
        $slug = $plugin instanceof Plugin ? $plugin->slug : $plugin;
        $plugin = $this->plugins->findBySlug($slug);

        if (! $plugin) {
            return $this->alreadyAbsentResult($slug);
        }

        return $this->flow->purge($plugin);
    }

    /**
     * A repeated purge is a successful no-op and must not create another audit
     * record. This makes browser retries and duplicate submissions safe.
     *
     * @return array<string, mixed>
     */
    private function alreadyAbsentResult(string $slug): array
    {
        return [
            'success' => true,
            'plugin' => $slug,
            'previous_status' => null,
            'completed_steps' => ['already_absent'],
            'failed_step' => null,
            'removed_resources' => [],
            'blocked_by_dependencies' => [],
            'message' => "Plugin [{$slug}] is already absent; no changes were required.",
            'data_policy' => 'purge',
            'already_absent' => true,
        ];
    }
}
