<?php

namespace App\Platform\Core\Updates;

class UpdateManager
{
    public function __construct(
        private readonly PluginUpdateChecker $pluginUpdates,
        private readonly UpdateRunner $runner,
    ) {
        //
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function checkPluginUpdates(): array
    {
        return $this->pluginUpdates->check();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function checkThemeUpdates(): array
    {
        return [];
    }

    public function updatePlugin(string $slug): UpdateResult
    {
        return $this->runner->updatePlugin($slug);
    }

    public function updateTheme(string $slug): UpdateResult
    {
        return UpdateResult::failure('theme', $slug, null, null, 'disabled', 'Theme updates are provided by the Theme Manager plugin.');
    }

    /**
     * @return array{plugins: array<string, array<string, mixed>>, themes: array<string, array<string, mixed>>}
     */
    public function getAvailableUpdates(): array
    {
        return [
            'plugins' => array_filter(
                $this->checkPluginUpdates(),
                fn (array $update): bool => (bool) ($update['update_available'] ?? false),
            ),
            'themes' => array_filter(
                $this->checkThemeUpdates(),
                fn (array $update): bool => (bool) ($update['update_available'] ?? false),
            ),
        ];
    }
}
