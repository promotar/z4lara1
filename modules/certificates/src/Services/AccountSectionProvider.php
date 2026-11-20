<?php

namespace Modules\Certificates\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Modules\Certificates\Models\Certificate;

final class AccountSectionProvider
{
    public function __construct(private readonly IssuerAccessService $access)
    {
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public function append(array $sections, User $user): array
    {
        if (! Schema::hasTable('certificate_issuers') || ! Schema::hasTable('certificates')) {
            return $sections;
        }

        if (! $this->access->isActive($user)) {
            return $sections;
        }

        $certificates = Certificate::query()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'name', 'date', 'slug']);

        $sections[] = [
            'key' => 'certificates',
            'label' => __('certificates::ui.certificates'),
            'icon' => 'C',
            'order' => 40,
            'badge' => (string) Certificate::query()->count(),
            'view' => 'certificates::account.manage',
            'data' => [
                'certificates' => $certificates,
                'dashboardUrl' => route('certificates.manage.index'),
                'createUrl' => route('certificates.manage.create'),
            ],
        ];

        return $sections;
    }
}
