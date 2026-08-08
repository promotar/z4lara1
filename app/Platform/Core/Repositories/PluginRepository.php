<?php

namespace App\Platform\Core\Repositories;

use App\Platform\Core\Models\Plugin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PluginRepository
{
    /**
     * @return Collection<int, Plugin>
     */
    public function all(): Collection
    {
        return Plugin::query()
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?Plugin
    {
        return Plugin::query()->find($id);
    }

    public function findBySlug(string $slug): ?Plugin
    {
        return Plugin::query()
            ->where('slug', $slug)
            ->first();
    }

    /**
     * @return Collection<int, Plugin>
     */
    public function findActive(): Collection
    {
        return Plugin::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Plugin
    {
        return Plugin::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Plugin $plugin, array $attributes): Plugin
    {
        $plugin->fill($attributes);
        $plugin->save();

        return $plugin->refresh();
    }

    public function markInstalled(Plugin $plugin): Plugin
    {
        return $this->update($plugin, [
            'status' => Plugin::STATUS_INSTALLED,
            'installed_at' => $plugin->installed_at ?? Carbon::now(),
        ]);
    }

    public function markActivated(Plugin $plugin): Plugin
    {
        return $this->update($plugin, [
            'status' => Plugin::STATUS_ACTIVE,
            'activated_at' => Carbon::now(),
            'disabled_at' => null,
        ]);
    }

    public function markDisabled(Plugin $plugin): Plugin
    {
        return $this->update($plugin, [
            'status' => Plugin::STATUS_DISABLED,
            'disabled_at' => Carbon::now(),
        ]);
    }

    public function delete(Plugin $plugin): bool
    {
        return (bool) $plugin->delete();
    }
}
