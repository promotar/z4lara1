<?php

namespace Modules\Exhibition\Infrastructure\Lifecycle;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03LuxuryHallSlotLayout;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

require_once dirname(__DIR__, 2).'/Support/autoload.php';

/**
 * Module-owned lifecycle callbacks. Core owns runtime gating, route and menu
 * registration, cache clearing, backups, uninstall validation, and table purge.
 */
final class ExhibitionLifecycle
{
    /**
     * Core protects capability-backed routes with an additional exact route
     * permission. Keep the artist role in sync when an update introduces new
     * virtual-exhibition endpoints so an ordinary plugin update is sufficient.
     *
     * @var list<string>
     */
    private const ARTIST_PERMISSIONS = [
        'exhibitions.artist.dashboard',
        'exhibitions.virtual.artist.view',
        'exhibitions.virtual.artist.submit',
        'exhibitions.virtual.artist.withdraw',
        'route.exhibitions.dashboard.virtual.index',
        'route.exhibitions.dashboard.virtual.place',
        'route.exhibitions.dashboard.virtual.place.artwork',
        'route.exhibitions.dashboard.virtual.manifest',
        'route.exhibitions.dashboard.virtual.holds.create',
        'route.exhibitions.dashboard.virtual.holds.refresh',
        'route.exhibitions.dashboard.virtual.holds.release',
        'route.exhibitions.dashboard.virtual.submit',
        'route.exhibitions.dashboard.virtual.move',
        'route.exhibitions.dashboard.virtual.withdraw',
        'route.exhibitions.dashboard.virtual.republish-request',
        'route.exhibitions.dashboard.virtual.resubmit',
    ];

    public function install(): void
    {
        $this->syncArtistRole();
        $this->syncPlacementGeometry();
        $this->audit('exhibitions.lifecycle.install');
    }

    public function activate(): void
    {
        $this->syncArtistRole();
        $this->syncPlacementGeometry();
        $this->audit('exhibitions.lifecycle.activate');
    }

    public function deactivate(): void
    {
        $this->audit('exhibitions.lifecycle.deactivate');
    }

    public function reactivate(): void
    {
        $this->syncArtistRole();
        $this->syncPlacementGeometry();
        $this->audit('exhibitions.lifecycle.reactivate');
    }

    /** Compatibility callback for pre-2.5 callers; platform 2.5 uses purge(). */
    public function uninstall(): void
    {
        $this->audit('exhibitions.lifecycle.uninstall', 'Legacy uninstall callback invoked without data removal.');
    }

    public function purge(): void
    {
        $this->audit('exhibitions.lifecycle.purge', 'Explicit destructive purge requested.');

        $disk = Storage::disk('local');
        if ($disk->directoryExists('exhibitions') && ! $disk->deleteDirectory('exhibitions')) {
            throw new RuntimeException('The Exhibitions private-data directory could not be purged safely.');
        }
    }

    private function audit(string $action, ?string $reason = null): void
    {
        if (! Schema::hasTable('exhibition_audit_entries')) {
            return;
        }

        $request = app()->bound('request') ? request() : null;
        $now = now();

        DB::table('exhibition_audit_entries')->insert([
            'public_id' => (string) Str::uuid(),
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'target_type' => 'module',
            'target_id' => 'exhibitions',
            'previous_values' => null,
            'new_values' => json_encode(['version' => (string) config('exhibitions.version', '1.6.7')], JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 1024, '') : null,
            'request_id' => $request?->headers->get('X-Request-Id'),
            'context' => json_encode(['source' => app()->runningInConsole() ? 'console' : 'http'], JSON_THROW_ON_ERROR),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function syncArtistRole(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $role = Role::findOrCreate('artist', 'web');
        $permissions = collect(self::ARTIST_PERMISSIONS)
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
        $role->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncPlacementGeometry(): void
    {
        if (! Schema::hasTable('exhibition_virtual_environment_versions')
            || ! Schema::hasTable('exhibition_virtual_environment_rooms')
            || ! Schema::hasTable('exhibition_virtual_environment_slots')) {
            return;
        }

        $this->slotLayout()->normalizeImportedVersions();
    }

    private function slotLayout(): Ve03LuxuryHallSlotLayout
    {
        return new Ve03LuxuryHallSlotLayout;
    }
}
