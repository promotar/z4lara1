@php
    try {
        $settings = app(\App\Platform\Core\Services\SettingsRepository::class)->values();
    } catch (\Throwable $exception) {
        $settings = [];
    }

    $isArabicLanguage = false;
    $t = fn (string $english, string $arabic): string => $isArabicLanguage ? $arabic : $english;

    $cards = [
        [
            'label' => $t('Settings', 'الإعدادات'),
            'description' => $t('Manage platform settings.', 'إدارة إعدادات المنصة.'),
            'route' => 'admin.settings.index',
            'permission' => 'settings.manage',
        ],
        [
            'label' => $t('Media', 'الوسائط'),
            'description' => $t('Upload and review media library files.', 'رفع ومراجعة ملفات مكتبة الوسائط.'),
            'route' => 'admin.media.index',
            'permission' => 'media.manage',
        ],
        [
            'label' => $t('Pages', 'الصفحات'),
            'description' => $t('Create and manage content pages.', 'إنشاء وإدارة صفحات المحتوى.'),
            'route' => 'admin.pages.index',
            'permission' => 'pages.manage',
        ],
        [
            'label' => $t('Themes', 'القوالب'),
            'description' => $t('Upload frontend and admin themes.', 'رفع وتفعيل قوالب الواجهة ولوحة الإدارة.'),
            'route' => 'admin.themes.index',
            'permission' => 'themes.manage',
        ],
        [
            'label' => $t('Plugins', 'الإضافات'),
            'description' => $t('View installed plugins.', 'عرض الإضافات المثبتة.'),
            'route' => 'admin.plugins.index',
            'permission' => 'plugins.view',
        ],
        [
            'label' => $t('Install Plugins', 'تثبيت الإضافات'),
            'description' => $t('Install discovered plugins.', 'تثبيت الإضافات المكتشفة.'),
            'route' => 'admin.plugins.create',
            'permission' => 'plugins.install',
        ],
        [
            'label' => $t('Users', 'المستخدمون'),
            'description' => $t('Create and update users.', 'إنشاء وتحديث المستخدمين.'),
            'route' => 'admin.users.index',
            'permission' => 'users.manage',
        ],
        [
            'label' => $t('Roles', 'الأدوار'),
            'description' => $t('Manage roles and role permissions.', 'إدارة الأدوار وصلاحياتها.'),
            'route' => 'admin.roles.index',
            'permission' => 'roles.manage',
        ],
        [
            'label' => $t('Permissions', 'الصلاحيات'),
            'description' => $t('Create and review platform permissions.', 'إنشاء ومراجعة صلاحيات المنصة.'),
            'route' => 'admin.permissions.index',
            'permission' => 'permissions.manage',
        ],
        [
            'label' => $t('Admin', 'الإدارة'),
            'description' => $t('Manage registry, documentation, logs, reports, and backups.', 'إدارة السجل والوثائق والسجلات والتقارير والنسخ الاحتياطية.'),
            'route' => 'admin.platform-registry.index',
            'super_admin_only' => true,
        ],
    ];

    $routeAccess = app(\App\Platform\Core\Access\RouteAccessGate::class);
    $visibleCards = collect($cards)->filter(fn (array $card): bool =>
        \Illuminate\Support\Facades\Route::has($card['route'])
        && $routeAccess->allowsRouteName(auth()->user(), $card['route'])
    );
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="ainpa-page-title">
            {{ $t('Dashboard', 'لوحة التحكم') }}
        </h2>
    </x-slot>

    <div class="ainpa-dashboard-page">
        <div class="ainpa-page-container">
            @if (session('status'))
                <div class="ainpa-alert ainpa-alert-warning">
                    {{ session('status') }}
                </div>
            @endif

            <div class="ainpa-dashboard-hero">
                <h3 class="ainpa-section-title">{{ $t('Available Tools', 'الأدوات المتاحة') }}</h3>
                <p class="ainpa-section-description">
                    {{ $t('Only tools allowed by your permissions are shown here.', 'تظهر هنا فقط الأدوات المسموحة حسب صلاحياتك.') }}
                </p>
            </div>

            @if ($visibleCards->isEmpty())
                <div class="ainpa-empty-state">
                    {{ $t('No admin tools are currently assigned to your account.', 'لا توجد أدوات إدارية مخصصة لحسابك حاليًا.') }}
                </div>
            @else
                <div class="ainpa-tool-grid">
                    @foreach ($visibleCards as $card)
                        <a href="{{ route($card['route']) }}" class="ainpa-tool-card">
                            <h3 class="ainpa-tool-card-title">{{ $card['label'] }}</h3>
                            <p class="ainpa-tool-card-description">{{ $card['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
