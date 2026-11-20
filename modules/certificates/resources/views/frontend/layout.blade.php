<x-frontend-layout>
    <x-slot name="head">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <meta name="referrer" content="no-referrer">
        <title>@yield('title', 'Certificate Management')</title>
        <link rel="stylesheet" href="{{ route('certificates.styles', ['v' => '1.3.0']) }}">
        @vite('resources/css/account.css')
        <style>
            .certificates-manage{direction:ltr;text-align:left}
            .ainpa-account-tabs a{display:flex;width:100%;min-height:2.85rem;gap:.65rem;align-items:center;padding:.65rem .8rem;border:1px solid transparent;border-radius:.7rem;background:transparent;color:#f6dadd;font:inherit;font-size:.88rem;font-weight:700;text-align:start;cursor:pointer;text-decoration:none}
            .ainpa-account-tabs a:hover{background:rgb(255 255 255 / 7%);color:#fff}
            .ainpa-account-tabs a.is-active{border-color:rgb(255 255 255 / 20%);background:#fff;color:var(--account-deep)}
            .ainpa-account-tabs a b{margin-inline-start:auto;padding:.18rem .42rem;border-radius:999px;background:rgb(255 255 255 / 13%);font-size:.68rem}
            .ainpa-account-tabs a.is-active b{background:var(--account-soft)}
            .ainpa-account-subnav{display:grid;gap:.2rem;margin:.15rem 0 .4rem;padding-inline-start:.85rem;border-inline-start:1px solid rgb(255 255 255 / 20%)}
            .ainpa-account-subnav a{display:block;padding:.34rem .5rem;border-radius:.5rem;color:#f0c9cc;font-size:.78rem;font-weight:700;text-decoration:none}
            .ainpa-account-subnav a:hover{background:rgb(255 255 255 / 8%);color:#fff}
            .ainpa-account-subnav a.is-active{background:rgb(255 255 255 / 18%);color:#fff}
            .certificates-manage .account-section-action{display:inline-flex;align-items:center;justify-content:center;min-height:50px;padding:10px 22px;border-radius:10px;background:var(--cert-plum) !important;color:#fff !important;font-size:15px;font-weight:800;text-decoration:none}
            .certificates-manage .account-section-action:hover{background:var(--cert-purple) !important;color:#fff !important}
            .certificates-manage [data-bulk-actions][hidden]{display:none !important}
            .certificates-manage .manage-head{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:18px}
            .certificates-manage .manage-head h1{margin:0}
            .certificates-manage .manage-search{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:18px}
            .certificates-manage input[type=text],.certificates-manage input[type=search],.certificates-manage input[type=date]{height:50px;min-width:240px;padding:10px 14px;border:1px solid color-mix(in srgb, var(--art-color-accent, #a980da) 55%, #fff);border-radius:10px;background:#fff;color:var(--cert-ink)}
            .certificates-manage input[type=checkbox]{width:18px;height:18px;min-width:0;cursor:pointer}
            .certificates-manage button{display:inline-block;min-height:50px;padding:10px 22px;border:0;border-radius:10px;background:var(--cert-plum);color:#fff;font-weight:800;cursor:pointer}
            .certificates-manage button:hover{background:var(--cert-purple)}
            .certificates-manage .manage-danger{min-height:44px;border:1px solid #dc2626;background:#fff;color:#b91c1c}
            .certificates-manage .manage-danger:hover{background:#fef2f2;color:#b91c1c}
            .certificates-manage .manage-danger-inline{min-height:0;margin-left:12px;padding:0;border:0;background:none;color:#b91c1c;font-weight:800;text-decoration:underline}
            .certificates-manage .manage-danger-inline:hover{background:none}
            .certificates-manage .manage-notice{margin-bottom:16px;padding:12px 16px;border-radius:12px;background:#eef7ee;color:#256029;font-weight:700}
            .certificates-manage .manage-notice.is-error{background:#fdecec;color:#9b1c1c}
            .certificates-manage .manage-notice ul{margin:0;padding-left:20px}
            .certificates-manage .manage-table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border:1px solid var(--cert-border);border-radius:16px;background:#fff}
            .certificates-manage .manage-table th,.certificates-manage .manage-table td{padding:14px 16px;text-align:left;vertical-align:middle;border-bottom:1px solid var(--cert-border)}
            .certificates-manage .manage-table th{background:#f7f3fb;color:var(--cert-plum);font-size:14px}
            .certificates-manage .manage-table tr:last-child td{border-bottom:0}
            .certificates-manage .manage-link{display:inline-block;margin-left:12px;color:var(--cert-purple);font-weight:800;text-decoration:none}
            .certificates-manage .manage-empty{padding:36px;text-align:center;color:var(--cert-muted)}
            .certificates-manage .manage-panel{max-width:872px;margin:0 auto;padding:32px;border:1px solid var(--cert-border);border-radius:18px;background:#fff}
            .certificates-manage .manage-panel h1{margin-top:0}
            .certificates-manage .manage-panel .field{margin-bottom:18px}
            .certificates-manage .manage-panel label{display:block;margin-bottom:8px;font-weight:800;color:var(--cert-plum)}
            .certificates-manage .manage-panel input[type=text],.certificates-manage .manage-panel input[type=date],.certificates-manage .manage-panel input[type=file]{width:100%;min-width:0}
            .certificates-manage .manage-preview{display:block;width:100%;height:auto;margin-top:12px;border:1px solid var(--cert-border);border-radius:12px}
            .certificates-manage .manage-actions-row{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:24px}
        </style>
    </x-slot>
    @php
        $portalUser = auth()->user();
        $portalSections = $portalUser ? app(\App\Platform\Core\Accounts\AccountSectionRegistry::class)->sections($portalUser) : [];
        $portalInitial = $portalUser ? mb_substr((string) $portalUser->name, 0, 1, 'UTF-8') : '?';
    @endphp
    <section class="ainpa-account-page" dir="auto">
        <div class="ainpa-account-shell">
            <div class="ainpa-account-workspace">
                <aside class="ainpa-account-sidebar">
                    @if($portalUser)
                        <div class="ainpa-account-profile-card">
                            <div class="ainpa-account-avatar" aria-hidden="true">{{ $portalInitial }}</div>
                            <div class="ainpa-account-profile-card__content">
                                <p>{{ __('certificates::ui.signed_in_as') }}</p>
                                <h2>{{ $portalUser->name }}</h2>
                                <span>{{ $portalUser->email }}</span>
                            </div>
                        </div>
                        <nav class="ainpa-account-tabs" aria-label="{{ __('certificates::ui.account_sections') }}">
                            @foreach($portalSections as $section)
                                <a href="{{ url('/account') }}#{{ $section['key'] }}" @class(['is-active' => $section['key'] === 'certificates'])>
                                    <span class="ainpa-account-tab-icon" aria-hidden="true">{{ $section['icon'] }}</span>
                                    <span>{{ $section['label'] }}</span>
                                    @if($section['badge'] !== null)<b>{{ $section['badge'] }}</b>@endif
                                </a>
                                @if($section['key'] === 'certificates')
                                    @php($trashView = request()->routeIs('certificates.manage.index') && request('view') === 'trash')
                                    <div class="ainpa-account-subnav" aria-label="{{ __('certificates::ui.certificate_sections') }}">
                                        <a href="{{ route('certificates.manage.index') }}" @class(['is-active' => request()->routeIs('certificates.manage.index') && ! $trashView])>{{ __('certificates::ui.all_certificates_lower') }}</a>
                                        <a href="{{ route('certificates.manage.create') }}" @class(['is-active' => request()->routeIs('certificates.manage.create')])>{{ __('certificates::ui.add_new_certificate') }}</a>
                                        <a href="{{ route('certificates.manage.index', ['view' => 'trash']) }}" @class(['is-active' => $trashView])>{{ __('certificates::ui.deleted_certificates') }}</a>
                                    </div>
                                @endif
                            @endforeach
                        </nav>
                        <div class="ainpa-account-sidebar__actions">
                            <form method="POST" action="{{ route('logout') }}" class="ainpa-account-logout">
                                @csrf
                                <button type="submit" class="ainpa-account-button ainpa-account-button--ghost">{{ __('certificates::ui.log_out') }}</button>
                            </form>
                        </div>
                    @endif
                </aside>
                <main class="ainpa-account-tab-panels">
                    <div role="tabpanel" style="min-height:0">
                        <div class="certificates-public certificates-manage" dir="ltr" lang="en">
                            <div class="certificate-page">@yield('content')</div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </section>
</x-frontend-layout>
