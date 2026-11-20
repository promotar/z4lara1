<x-app-layout>
    @include('certificates::admin.styles')
    <style>
        .cert-issuers{max-width:1180px;margin:0 auto;padding:24px 22px 36px;color:var(--ainpa-admin-text, #1f2937)}
        .cert-issuers *{box-sizing:border-box}
        .cert-issuers__head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:16px}
        .cert-issuers__head h1{margin:0;font-size:25px}
        .cert-issuers__head p{margin:5px 0 0;color:var(--ainpa-admin-muted, #6b7280);font-size:13px}
        .cert-issuers__notice{margin-bottom:12px;border-left:4px solid #15803d;border-radius:7px;background:#ecfdf5;padding:10px 13px;color:#166534;font-size:12px}
        .cert-issuers__notice.is-error{border-color:#dc2626;background:#fff1f2;color:#991b1b}
        .cert-issuers__card{margin-bottom:14px;border:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #e6d7ea) 14%, #fff);border-radius:11px;background:#fff;box-shadow:0 6px 22px rgba(54,35,76,.05)}
        .cert-issuers__card-head{border-bottom:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #efe6f2) 10%, #fff);padding:15px 17px}
        .cert-issuers__card-head h2{margin:0;font-size:16px}
        .cert-issuers__card-head p{margin:4px 0 0;color:#5f5566;font-size:11px}
        .cert-issuers__add{display:grid;grid-template-columns:minmax(240px,1fr) auto;gap:10px;padding:16px}
        .cert-issuers__lookup{position:relative}
        .cert-issuers input,.cert-issuers select{width:100%;min-height:40px;border:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #cbb8d6) 26%, #fff);border-radius:7px;background:#fff;padding:8px 10px}
        .cert-issuers button:not(.cert-issuers__result),.cert-issuers__button{display:inline-flex;min-height:38px;align-items:center;justify-content:center;border:1px solid var(--ainpa-admin-accent, #5b2e86);border-radius:7px;background:var(--ainpa-admin-accent, #5b2e86);padding:0 14px;color:#fff;font-size:12px;font-weight:800;text-decoration:none;cursor:pointer}
        .cert-issuers__button.is-danger{border-color:#dc2626;background:#fff;color:#b91c1c}
        .cert-issuers__results{position:absolute;z-index:20;top:44px;right:0;left:0;display:none;overflow:auto;max-height:230px;border:1px solid #d8c6e0;border-radius:8px;background:#fff;box-shadow:0 12px 30px rgba(30,20,40,.14)}
        .cert-issuers__results.is-open{display:block}
        .cert-issuers__result{display:block;width:100%;border:0;border-bottom:1px solid #f0e8f4;background:#fff;padding:9px 11px;color:var(--ainpa-admin-text, #1f2937);text-align:left;cursor:pointer}
        .cert-issuers__result:hover{background:color-mix(in srgb, var(--ainpa-admin-accent, #faf5fd) 5%, #fff)}
        .cert-issuers__result strong,.cert-issuers__result small{display:block}
        .cert-issuers__result small{margin-top:2px;color:var(--ainpa-admin-muted, #6b7280)}
        .cert-issuers__selected{margin-top:5px;color:#15803d;font-size:11px}
        .cert-issuers__filters{display:flex;gap:8px;padding:12px 16px;border-bottom:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #efe6f2) 10%, #fff)}
        .cert-issuers__filters input{flex:1}
        .cert-issuers__list{display:grid}
        .cert-issuer-row{display:grid;grid-template-columns:minmax(220px,1fr) minmax(280px,1fr) auto;gap:14px;align-items:center;padding:13px 16px;border-bottom:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #f2ecf6) 7%, #fff)}
        .cert-issuer-row:last-child{border-bottom:0}
        .cert-issuer-row__identity strong,.cert-issuer-row__identity small{display:block}
        .cert-issuer-row__identity small{margin-top:3px;color:var(--ainpa-admin-muted, #6b7280)}
        .cert-issuer-row__form{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center}
        .cert-issuers__empty{padding:26px;text-align:center;color:var(--ainpa-admin-muted, #6b7280)}
        @media(max-width:820px){.cert-issuers__add,.cert-issuer-row,.cert-issuer-row__form{grid-template-columns:1fr}.cert-issuer-row{align-items:stretch}.cert-issuers__head{flex-direction:column}}
    </style>

    <main class="cert-issuers" dir="ltr">
        <header class="cert-issuers__head">
            <div><h1>{{ __('certificates::ui.issuers_title') }}</h1><p>{{ __('certificates::ui.issuers_intro') }}</p></div>
        </header>

        @if(session('status'))<div class="cert-issuers__notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="cert-issuers__notice is-error">{{ $errors->first() }}</div>@endif

        <section class="cert-issuers__card">
            <div class="cert-issuers__card-head"><h2>{{ __('certificates::ui.add_issuer') }}</h2><p>{{ __('certificates::ui.add_issuer_hint') }}</p></div>
            <form method="POST" action="{{ route('admin.plugins.certificates.issuers.store', [], false) }}" class="cert-issuers__add" id="issuer-add-form">
                @csrf
                <div class="cert-issuers__lookup">
                    <input id="issuer-user-search" type="search" autocomplete="off" placeholder="{{ __('certificates::ui.search_by_name_email') }}" aria-label="{{ __('certificates::ui.search_users') }}">
                    <input id="issuer-user-id" type="hidden" name="user_id" value="{{ old('user_id') }}">
                    <div class="cert-issuers__results" id="issuer-user-results" role="listbox"></div>
                    <div class="cert-issuers__selected" id="issuer-user-selected"></div>
                </div>
                <button type="submit">{{ __('certificates::ui.add_issuer') }}</button>
            </form>
        </section>

        <section class="cert-issuers__card">
            <div class="cert-issuers__card-head"><h2>{{ __('certificates::ui.current_issuers') }}</h2><p>{{ __('certificates::ui.current_issuers_hint') }}</p></div>
            <form method="GET" class="cert-issuers__filters"><input type="text" name="search" value="{{ $search }}" placeholder="{{ __('certificates::ui.search_issuers') }}"><button type="submit">{{ __('certificates::ui.search') }}</button></form>
            <div class="cert-issuers__list">
                @forelse($issuers as $issuer)
                    <div class="cert-issuer-row">
                        <div class="cert-issuer-row__identity"><strong>{{ $issuer->user?->name ?: 'Unavailable user' }}</strong><small>{{ $issuer->user?->email }}</small></div>
                        <form class="cert-issuer-row__form" method="POST" action="{{ route('admin.plugins.certificates.issuers.update', $issuer, false) }}">
                            @csrf @method('PUT')
                            <select name="status" aria-label="{{ __('certificates::ui.issuer_status') }}"><option value="active" @selected($issuer->status === 'active')>{{ __('certificates::ui.active') }}</option><option value="suspended" @selected($issuer->status === 'suspended')>{{ __('certificates::ui.suspended') }}</option></select>
                            <button type="submit">{{ __('certificates::ui.save') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.plugins.certificates.issuers.destroy', $issuer, false) }}" onsubmit="return confirm(@js(__('certificates::ui.confirm_remove_issuer')));">@csrf @method('DELETE')<button class="cert-issuers__button is-danger" type="submit">{{ __('certificates::ui.remove') }}</button></form>
                    </div>
                @empty
                    <div class="cert-issuers__empty">{{ __('certificates::ui.no_issuers_yet') }}</div>
                @endforelse
            </div>
        </section>

        <div style="margin-top:16px">{{ $issuers->links() }}</div>
    </main>

    <script>
        (function () {
            var input = document.getElementById('issuer-user-search');
            var idField = document.getElementById('issuer-user-id');
            var results = document.getElementById('issuer-user-results');
            var selected = document.getElementById('issuer-user-selected');
            var form = document.getElementById('issuer-add-form');
            var endpoint = @json(route('admin.plugins.certificates.issuers.users', [], false));
            var timer;
            if (!input || !form) { return; }
            input.addEventListener('input', function () {
                idField.value = '';
                selected.textContent = '';
                clearTimeout(timer);
                var query = input.value.trim();
                if (query.length < 2) { results.classList.remove('is-open'); results.innerHTML = ''; return; }
                timer = setTimeout(function () {
                    fetch(endpoint + '?q=' + encodeURIComponent(query), { headers: { 'Accept': 'application/json' } })
                        .then(function (response) { return response.ok ? response.json() : { items: [] }; })
                        .then(function (data) {
                            results.innerHTML = '';
                            (data.items || []).forEach(function (user) {
                                var button = document.createElement('button');
                                button.type = 'button';
                                button.className = 'cert-issuers__result';
                                var strong = document.createElement('strong');
                                strong.textContent = user.name;
                                var small = document.createElement('small');
                                small.textContent = user.email;
                                button.appendChild(strong);
                                button.appendChild(small);
                                button.addEventListener('click', function () {
                                    idField.value = user.id;
                                    input.value = user.name;
                                    selected.textContent = 'Selected: ' + user.name + ' (' + user.email + ')';
                                    results.classList.remove('is-open');
                                });
                                results.appendChild(button);
                            });
                            if (!(data.items || []).length) {
                                results.innerHTML = '<div class="cert-issuers__empty">No available users found.</div>';
                            }
                            results.classList.add('is-open');
                        });
                }, 250);
            });
            document.addEventListener('click', function (event) {
                if (!event.target.closest('.cert-issuers__lookup')) { results.classList.remove('is-open'); }
            });
            form.addEventListener('submit', function (event) {
                if (!idField.value) { event.preventDefault(); selected.textContent = 'Choose a user from the search results.'; input.focus(); }
            });
        })();
    </script>
</x-app-layout>
