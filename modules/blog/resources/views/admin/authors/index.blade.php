<x-app-layout>
    <style>
        .blog-authors{max-width:1180px;margin:0 auto;padding:24px 22px 36px;color:var(--ainpa-admin-text, #1f2937)}.blog-authors *{box-sizing:border-box}.blog-authors__head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:16px}.blog-authors__head h1{margin:0;font-size:25px}.blog-authors__head p{margin:5px 0 0;color:#6b7280;font-size:13px}.blog-authors__notice{margin-bottom:12px;border-left:4px solid #15803d;border-radius:7px;background:#ecfdf5;padding:10px 13px;color:#166534;font-size:12px}.blog-authors__notice.is-error{border-color:#dc2626;background:#fff1f2;color:#991b1b}.blog-authors__card{margin-bottom:14px;border:1px solid #ead2d2;border-radius:11px;background:#fff;box-shadow:0 6px 22px rgba(80,20,20,.05)}.blog-authors__card-head{border-bottom:1px solid #efdddd;padding:15px 17px}.blog-authors__card-head h2{margin:0;font-size:16px}.blog-authors__card-head p{margin:4px 0 0;color:#786767;font-size:11px}.blog-authors__add{display:grid;grid-template-columns:minmax(240px,1fr) 210px auto;gap:10px;padding:16px}.blog-authors__lookup{position:relative}.blog-authors input,.blog-authors select{width:100%;min-height:40px;border:1px solid #dcbcbc;border-radius:7px;background:#fff;padding:8px 10px}.blog-authors button:not(.blog-authors__result),.blog-authors__button{display:inline-flex;min-height:38px;align-items:center;justify-content:center;border:1px solid var(--ainpa-admin-accent, #a90000);border-radius:7px;background:var(--ainpa-admin-accent, #a90000);padding:0 14px;color:#fff;font-size:12px;font-weight:800;text-decoration:none;cursor:pointer}.blog-authors__button.is-danger{border-color:#dc2626;background:#fff;color:#b91c1c}.blog-authors__results{position:absolute;z-index:20;top:44px;right:0;left:0;display:none;overflow:auto;max-height:230px;border:1px solid #dababa;border-radius:8px;background:#fff;box-shadow:0 12px 30px rgba(30,20,20,.14)}.blog-authors__results.is-open{display:block}.blog-authors__result{display:block;width:100%;border:0;border-bottom:1px solid #f0e1e1;background:#fff;padding:9px 11px;color:var(--ainpa-admin-text, #1f2937);text-align:left;cursor:pointer}.blog-authors__result:hover{background:#fff4f4}.blog-authors__result strong,.blog-authors__result small{display:block}.blog-authors__result small{margin-top:2px;color:#6b7280}.blog-authors__selected{margin-top:5px;color:#15803d;font-size:11px}.blog-authors__filters{display:flex;gap:8px;padding:12px 16px;border-bottom:1px solid #efdddd}.blog-authors__filters input{flex:1}.blog-authors__list{display:grid}.blog-author-row{display:grid;grid-template-columns:minmax(220px,1fr) minmax(360px,1.25fr) auto;gap:14px;align-items:center;padding:13px 16px;border-bottom:1px solid #f1e3e3}.blog-author-row:last-child{border-bottom:0}.blog-author-row__identity strong,.blog-author-row__identity small{display:block}.blog-author-row__identity small{margin-top:3px;color:#6b7280}.blog-author-row__form{display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:center}.blog-author-row__empty{padding:26px;text-align:center;color:#6b7280}@media(max-width:820px){.blog-authors__add,.blog-author-row,.blog-author-row__form{grid-template-columns:1fr}.blog-author-row{align-items:stretch}.blog-authors__head{flex-direction:column}}
    </style>

    <main class="blog-authors">
        <header class="blog-authors__head">
            <div><h1>{{ __('blog::ui.auto_02684cc6b6') }}</h1><p>{{ __('blog::ui.auto_727c1699e8') }}</p></div>
            <a class="blog-authors__button is-danger" href="{{ route('admin.plugins.blog.settings.edit', [], false) }}">{{ __('blog::ui.auto_546d2191a4') }}</a>
        </header>

        @if(session('status'))<div class="blog-authors__notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="blog-authors__notice is-error">{{ $errors->first() }}</div>@endif

        <section class="blog-authors__card">
            <div class="blog-authors__card-head"><h2>{{ __('blog::ui.auto_c1aa052dfa') }}</h2><p>{{ __('blog::ui.auto_8bcb81025a') }}</p></div>
            <form method="POST" action="{{ route('admin.plugins.blog.authors.store', [], false) }}" class="blog-authors__add" id="author-add-form">
                @csrf
                <div class="blog-authors__lookup">
                    <input id="author-user-search" type="search" autocomplete="off" placeholder="{{ __('blog::ui.auto_09f86f0152') }}" aria-label="{{ __('blog::ui.auto_2672837433') }}">
                    <input id="author-user-id" type="hidden" name="user_id" value="{{ old('user_id') }}">
                    <div class="blog-authors__results" id="author-user-results" role="listbox"></div>
                    <div class="blog-authors__selected" id="author-user-selected"></div>
                </div>
                <select name="publishing_policy" aria-label="{{ __('blog::ui.auto_42575dd6c4') }}">
                    <option value="review" @selected(old('publishing_policy') === 'review')>{{ __('blog::ui.auto_67f98034e4') }}</option>
                    <option value="direct" @selected(old('publishing_policy') === 'direct')>{{ __('blog::ui.auto_0c9fef3a15') }}</option>
                </select>
                <button type="submit">{{ __('blog::ui.auto_f35345220d') }}</button>
            </form>
        </section>

        <section class="blog-authors__card">
            <div class="blog-authors__card-head"><h2>{{ __('blog::ui.auto_61c376f419') }}</h2><p>{{ __('blog::ui.auto_8607c9fdb3') }}</p></div>
            <form method="GET" class="blog-authors__filters"><input name="search" value="{{ $search }}" placeholder="{{ __('blog::ui.auto_c39537273a') }}"><button type="submit">{{ __('blog::ui.auto_13348442cc') }}</button></form>
            <div class="blog-authors__list">
                @forelse($authors as $author)
                    <div class="blog-author-row">
                        <div class="blog-author-row__identity"><strong>{{ $author->user?->name ?: 'Unavailable user' }}</strong><small>{{ $author->user?->email }}</small></div>
                        <form class="blog-author-row__form" method="POST" action="{{ route('admin.plugins.blog.authors.update', $author, false) }}">
                            @csrf @method('PUT')
                            <select name="status" aria-label="{{ __('blog::ui.auto_d07bc0c159') }}"><option value="active" @selected($author->status === 'active')>{{ __('blog::ui.auto_4d3d769b81') }}</option><option value="suspended" @selected($author->status === 'suspended')>{{ __('blog::ui.auto_8bf906833c') }}</option></select>
                            <select name="publishing_policy" aria-label="{{ __('blog::ui.auto_42575dd6c4') }}"><option value="review" @selected(! $author->can_publish_directly)>{{ __('blog::ui.auto_67f98034e4') }}</option><option value="direct" @selected($author->can_publish_directly)>{{ __('blog::ui.auto_0c9fef3a15') }}</option></select>
                            <button type="submit">{{ __('blog::ui.auto_c9cc8cce24') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.plugins.blog.authors.destroy', $author, false) }}" onsubmit="return confirm('Remove this author access? Existing posts will remain.');">@csrf @method('DELETE')<button class="blog-authors__button is-danger" type="submit">{{ __('blog::ui.auto_1063e38cb5') }}</button></form>
                    </div>
                @empty
                    <div class="blog-author-row__empty">{{ __('blog::ui.auto_8d5aa01f33') }}</div>
                @endforelse
            </div>
        </section>
        <x-platform-pagination :paginator="$authors" />
    </main>

    <script>
        (() => {
            const input = document.getElementById('author-user-search');
            const id = document.getElementById('author-user-id');
            const results = document.getElementById('author-user-results');
            const selected = document.getElementById('author-user-selected');
            const endpoint = @json(route('admin.plugins.blog.authors.users', [], false));
            let timer;
            input.addEventListener('input', () => {
                id.value = '';
                selected.textContent = '';
                clearTimeout(timer);
                const query = input.value.trim();
                if (query.length < 2) { results.classList.remove('is-open'); results.innerHTML = ''; return; }
                timer = setTimeout(async () => {
                    const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {headers:{'Accept':'application/json'}});
                    const data = response.ok ? await response.json() : {items:[]};
                    results.innerHTML = '';
                    (data.items || []).forEach(user => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'blog-authors__result';
                        button.innerHTML = `<strong>${escapeHtml(user.name)}</strong><small>${escapeHtml(user.email)}</small>`;
                        button.addEventListener('click', () => {
                            id.value = user.id;
                            input.value = user.name;
                            selected.textContent = `Selected: ${user.name} (${user.email})`;
                            results.classList.remove('is-open');
                        });
                        results.appendChild(button);
                    });
                    if (!(data.items || []).length) results.innerHTML = '<div class="blog-author-row__empty">No available users found.</div>';
                    results.classList.add('is-open');
                }, 250);
            });
            document.addEventListener('click', event => { if (!event.target.closest('.blog-authors__lookup')) results.classList.remove('is-open'); });
            document.getElementById('author-add-form').addEventListener('submit', event => { if (!id.value) { event.preventDefault(); selected.textContent = 'Choose a user from the search results.'; input.focus(); } });
            function escapeHtml(value) { const node = document.createElement('span'); node.textContent = value || ''; return node.innerHTML; }
        })();
    </script>
</x-app-layout>
