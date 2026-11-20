<?php

namespace Modules\Exhibition\Application\Security;

use Illuminate\Support\Facades\DB;

final class ExhibitionSecurityPosture
{
    public function permissionMatrix(): array
    {
        return [
            'Module access' => ['permission' => 'exhibitions.access', 'scope' => 'Administration shell and permission-filtered dashboard'],
            'Sensitive settings' => ['permission' => 'exhibitions.manage_settings', 'scope' => 'Versioned module-owned settings and Store integration policy'],
            'Destructive settings purge' => ['permission' => 'exhibitions.purge_data', 'scope' => 'Explicit confirmation, reason, audit, and Exhibitions-only deletion'],
            'Artist and artwork review' => ['permission' => 'exhibitions.review', 'scope' => 'Applications, identity verification, authenticity, pricing and review'],
            'Operations' => ['permission' => 'exhibitions.operations', 'scope' => 'Inventory, custody, pickup and fulfillment'],
            'Finance' => ['permission' => 'exhibitions.finance', 'scope' => 'Immutable ledger, settlements and payout attempts'],
            'Customer support' => ['permission' => 'exhibitions.support', 'scope' => 'Cases, disputes and their private evidence'],
            'Reports' => ['permission' => 'exhibitions.reports', 'scope' => 'PII-free live reports and matching exports'],
            'Security posture' => ['permission' => 'exhibitions.security', 'scope' => 'Security controls, audit coverage and retention policy'],
        ];
    }

    public function privateAssets(): array
    {
        return [
            ['asset' => 'Identity and agreement files', 'control' => 'Authenticated ownership or review permission; signed temporary artist links', 'retention' => 'Retain while legally or operationally required, then approved scoped purge'],
            ['asset' => 'Artwork originals and evidence', 'control' => 'Private local disk, MIME/signature validation, signed temporary display and original links', 'retention' => 'Sold/order-linked originals are immutable; rejected drafts follow approved retention schedule'],
            ['asset' => 'Payout and bank data', 'control' => 'Finance-only server authorization; never selected by artist reports or notifications', 'retention' => 'Retain statutory settlement period; deletion requires finance/legal approval'],
            ['asset' => 'Customer and dispute data', 'control' => 'Case-party ownership or support permission; no artist access outside assigned case', 'retention' => 'Retain through dispute and chargeback windows, then minimize or purge by explicit policy'],
        ];
    }

    public function auditCoverage(): array
    {
        $areas = ['artists' => 'Application, verification, agreement and account-state changes', 'artwork' => 'Draft revisions, lifecycle, authenticity, price, review and publication', 'fulfillment' => 'Inventory, reservation, custody, shipping and delivery', 'dispute' => 'Case, message, evidence and adjudication changes', 'finance' => 'Settlement events, payout attempts and append-only ledger entries', 'settings' => 'Versioned settings and notification-template history', 'lifecycle' => 'Install, activation, deactivation, uninstall and explicit purge'];

        return collect($areas)->map(function (string $protection, string $area): array {
            $events = DB::table('exhibition_audit_entries')->where(function ($query) use ($area): void {
                $query->where('target_type', 'like', '%'.$area.'%')->orWhere('action', 'like', 'exhibitions.%'.$area.'%');
            })->count();

            return compact('area', 'events', 'protection');
        })->values()->all();
    }

    public function guarantees(): array
    {
        return [
            'All state-changing web requests use the platform web stack, authentication, CSRF protection and server-side authorization.',
            'Artist-owned records are always resolved through the authenticated artist identity; cross-account identifiers return not found.',
            'Private files are stored outside the public web root and sensitive artist links are signed and time limited.',
            'Uploads verify decoded image bytes, MIME signature, extension, dimensions and size before derivative processing.',
            'Sold artworks, completed-order data and financial ledger entries are not permanently editable or deletable.',
            'Deactivate and uninstall retain module data; only an explicit platform purge may remove the declared Exhibition tables.',
            'Logs, notification contexts and report exports exclude identity numbers, bank details, addresses, phones and customer PII.',
        ];
    }
}
