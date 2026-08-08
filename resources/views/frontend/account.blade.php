<x-frontend-layout>
    @php
        $user = auth()->user();
        $canAccessDashboard = $user->hasAnyRole(['super-admin', 'admin', 'staff', 'employee']);
        $accountInitial = function_exists('mb_substr')
            ? mb_substr((string) $user->name, 0, 1, 'UTF-8')
            : substr((string) $user->name, 0, 1);
        $roleLabels = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect();
    @endphp

    <section class="ainpa-account-page" dir="auto">
        <div class="ainpa-account-shell">
            <div class="ainpa-account-hero">
                <div class="ainpa-account-hero__content">
                    <p class="ainpa-account-kicker">Customer Area</p>
                    <h1>My Account</h1>
                    <p>
                        مساحة شخصية داخل الواجهة الأمامية لمتابعة بيانات الحساب والوصول السريع للخدمات العامة.
                    </p>
                </div>

                <div class="ainpa-account-profile-card" aria-label="Account summary">
                    <div class="ainpa-account-avatar" aria-hidden="true">
                        {{ $accountInitial }}
                    </div>
                    <div>
                        <p class="ainpa-account-profile-card__label">Signed in as</p>
                        <h2>{{ $user->name }}</h2>
                        <p>{{ $user->email }}</p>
                    </div>
                </div>
            </div>

            <div class="ainpa-account-grid">
                <article class="ainpa-account-card">
                    <span class="ainpa-account-card__icon" aria-hidden="true">ID</span>
                    <p class="ainpa-account-card__label">Name</p>
                    <h2>{{ $user->name }}</h2>
                    <p class="ainpa-account-card__text">اسم الحساب المعتمد داخل المنصة.</p>
                </article>

                <article class="ainpa-account-card">
                    <span class="ainpa-account-card__icon" aria-hidden="true">@</span>
                    <p class="ainpa-account-card__label">Email</p>
                    <h2>{{ $user->email }}</h2>
                    <p class="ainpa-account-card__text">البريد المستخدم لتسجيل الدخول والتنبيهات.</p>
                </article>

                <article class="ainpa-account-card">
                    <span class="ainpa-account-card__icon" aria-hidden="true">AC</span>
                    <p class="ainpa-account-card__label">Access</p>
                    <h2>
                        @if ($canAccessDashboard)
                            Staff dashboard enabled
                        @else
                            Frontend account only
                        @endif
                    </h2>
                    <p class="ainpa-account-card__text">
                        @if ($canAccessDashboard)
                            لديك صلاحية دخول للوحة الداخلية إضافة إلى حساب الواجهة.
                        @else
                            هذا الحساب مخصص للاستخدام داخل واجهة الموقع.
                        @endif
                    </p>
                </article>
            </div>

            <div class="ainpa-account-panel">
                <div>
                    <p class="ainpa-account-kicker">Account Status</p>
                    <h2>
                        @if ($canAccessDashboard)
                            حسابك مرتبط بصلاحيات تشغيل داخلية
                        @else
                            حساب مستخدم أمامي نشط
                        @endif
                    </h2>
                    <p>
                        @if ($canAccessDashboard)
                            يمكنك فتح لوحة التحكم عند الحاجة، مع بقاء هذه الصفحة كمساحة حساب أمامية منفصلة عن الإدارة.
                        @else
                            يمكنك استخدام هذه الصفحة كبوابة حسابك الأساسية داخل موقع Art INPA.
                        @endif
                    </p>

                    @if ($roleLabels->isNotEmpty())
                        <div class="ainpa-account-badges" aria-label="Assigned roles">
                            @foreach ($roleLabels as $roleLabel)
                                <span>{{ $roleLabel }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="ainpa-account-actions">
                    @if (Route::has('blog.index') && app(\App\Platform\Core\Services\PluginRuntimeGate::class)->allows('blog'))
                        <a href="{{ route('blog.index') }}" class="ainpa-account-button ainpa-account-button--primary">
                            Browse News
                        </a>
                    @endif

                    @if ($canAccessDashboard)
                        <a href="{{ route('dashboard') }}" class="ainpa-account-button">
                            Open Dashboard
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="ainpa-account-logout">
                        @csrf
                        <button type="submit" class="ainpa-account-button ainpa-account-button--ghost">
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-frontend-layout>
