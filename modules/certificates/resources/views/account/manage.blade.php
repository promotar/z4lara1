<section class="account-extension-section">
    <div class="account-section-heading">
        <div><h2>{{ __('certificates::ui.certificates') }}</h2><p>{{ __('certificates::ui.issue_subtitle') }}</p></div>
        <a class="account-section-action" href="{{ $createUrl }}">{{ __('certificates::ui.issue_certificate') }}</a>
    </div>

    <div class="account-item-list">
        @forelse($certificates as $certificate)
            <div class="account-item">
                <a href="{{ route('certificates.manage.edit', $certificate, false) }}" style="display:flex;flex:1;min-width:0;gap:1rem;align-items:center;justify-content:space-between;color:inherit;text-decoration:none">
                    <span><strong>{{ $certificate->name }}</strong><small>{{ $certificate->date?->format('Y-m-d') }} &middot; {{ $certificate->slug }}</small></span>
                    <span>{{ __('certificates::ui.edit') }}</span>
                </a>
                <form method="POST" action="{{ route('certificates.manage.destroy', $certificate, false) }}" style="margin:0;flex:0 0 auto" onsubmit="return confirm(@js(__('certificates::ui.confirm_move_one_account')));">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="border:1px solid var(--account-border);border-radius:.6rem;background:transparent;color:#b91c1c;cursor:pointer;font-size:.8rem;font-weight:800;padding:.45rem .7rem">{{ __('certificates::ui.delete') }}</button>
                </form>
            </div>
        @empty
            <a class="account-item" href="{{ $createUrl }}"><span><strong>{{ __('certificates::ui.no_certificates_yet') }}</strong><small>{{ __('certificates::ui.start_issuing') }}</small></span><span>{{ __('certificates::ui.issue') }}</span></a>
        @endforelse
    </div>

    <div class="account-section-heading">
        <p>{{ __('certificates::ui.manage_all') }}</p>
        <a class="account-section-action" href="{{ $dashboardUrl }}">{{ __('certificates::ui.all_certificates') }}</a>
    </div>
</section>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var key = (window.location.hash || '').replace('#', '');
            if (!key) { return; }
            var tab = document.getElementById('account-tab-' + key);
            if (tab) { tab.click(); tab.scrollIntoView({ block: 'nearest' }); }
        });
    </script>
@endonce

@once
    <style>
        .account-subnav{display:grid;gap:.2rem;margin:.15rem 0 .45rem;padding-inline-start:.85rem;border-inline-start:1px solid rgb(255 255 255 / 20%)}
        .account-subnav a{display:block;padding:.34rem .5rem;border-radius:.5rem;color:#f0c9cc;font-size:.78rem;font-weight:700;text-decoration:none}
        .account-subnav a:hover{background:rgb(255 255 255 / 8%);color:#fff}
        .account-subnav[hidden]{display:none !important}
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tab = document.getElementById('account-tab-certificates');
            if (!tab || document.getElementById('certificates-subnav')) { return; }
            var items = [
                ['All certificates', '{{ route('certificates.manage.index', [], false) }}'],
                ['Add new certificate', '{{ route('certificates.manage.create', [], false) }}'],
                ['Deleted certificates', '{{ route('certificates.manage.index', ['view' => 'trash'], false) }}']
            ];
            var nav = document.createElement('nav');
            nav.id = 'certificates-subnav';
            nav.className = 'account-subnav';
            nav.setAttribute('aria-label', 'Certificate sections');
            items.forEach(function (item) {
                var a = document.createElement('a');
                a.href = item[1];
                a.textContent = item[0];
                nav.appendChild(a);
            });
            tab.insertAdjacentElement('afterend', nav);
            var sync = function () { nav.hidden = ! tab.classList.contains('is-active'); };
            new MutationObserver(sync).observe(tab, { attributes: true, attributeFilter: ['class'] });
            sync();
        });
    </script>
@endonce
