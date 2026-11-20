<x-app-layout>
    @include('certificates::admin.styles')
    <style>
        .ct-editor{max-width:1280px;margin:0 auto;padding:22px 20px 40px;color:var(--ainpa-admin-text, #1f2937)}
        .ct-editor *{box-sizing:border-box}
        .ct-editor__head{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;margin-bottom:14px}
        .ct-editor__head h1{margin:0;font-size:24px}
        .ct-editor__head p{margin:5px 0 0;color:var(--ainpa-admin-muted, #6b7280);font-size:13px}
        .ct-editor__btn{display:inline-flex;min-height:38px;align-items:center;justify-content:center;border:1px solid var(--ainpa-admin-accent, #5b2e86);border-radius:8px;background:var(--ainpa-admin-accent, #5b2e86);padding:0 14px;color:#fff;font-size:12px;font-weight:800;text-decoration:none;cursor:pointer}
        .ct-editor__btn.is-light{border-color:color-mix(in srgb, var(--ainpa-admin-accent, #cbb8d6) 26%, #fff);background:#fff;color:var(--ainpa-admin-accent, #5b2e86)}
        .ct-editor__btn.is-danger{border-color:#dc2626;background:#fff;color:#b91c1c}
        .ct-editor__notice{margin-bottom:12px;border-left:4px solid #15803d;border-radius:7px;background:#ecfdf5;padding:10px 13px;color:#166534;font-size:12px}
        .ct-editor__notice.is-error{border-color:#dc2626;background:#fff1f2;color:#991b1b}
        .ct-editor__grid{display:grid;grid-template-columns:300px minmax(0,1fr);gap:16px;align-items:start}
        .ct-editor__card{border:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #e6d7ea) 14%, #fff);border-radius:12px;background:#fff;box-shadow:0 6px 22px rgba(54,35,76,.05)}
        .ct-editor__card-head{border-bottom:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #efe6f2) 10%, #fff);padding:13px 15px;font-weight:800;font-size:14px}
        .ct-editor__list{display:grid}
        .ct-tpl{display:block;padding:11px 14px;border-bottom:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #f2ecf6) 7%, #fff);text-decoration:none;color:var(--ainpa-admin-text, #1f2937)}
        .ct-tpl:hover{background:color-mix(in srgb, var(--ainpa-admin-accent, #faf5fd) 5%, #fff)}
        .ct-tpl.is-active{background:color-mix(in srgb, var(--ainpa-admin-accent, #f5edfb) 8%, #fff);box-shadow:inset 3px 0 0 var(--ainpa-admin-accent, #5b2e86)}
        .ct-tpl strong{display:block;font-size:13px}
        .ct-tpl small{display:block;margin-top:3px;color:var(--ainpa-admin-muted, #6b7280);font-size:11px}
        .ct-tpl b{display:inline-block;margin:6px 6px 0 0;border-radius:999px;background:#f1e9f7;padding:2px 8px;color:var(--ainpa-admin-accent, #5b2e86);font-size:10px;font-weight:800}
        .ct-tpl b.is-default{background:#e7f6ec;color:#166534}
        .ct-form{padding:16px}
        .ct-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:14px}
        .ct-field label{display:block;margin-bottom:6px;font-weight:800;font-size:12px;color:#4c1d95}
        .ct-field input,.ct-field select,.ct-field textarea{width:100%;border:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #cbb8d6) 26%, #fff);border-radius:8px;background:#fff;padding:9px 10px;font-size:13px}
        .ct-field textarea{min-height:340px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;line-height:1.55;white-space:pre;direction:ltr;text-align:left}
        .ct-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}
        .ct-tab{border:1px solid #d8c6e0;border-radius:8px 8px 0 0;background:color-mix(in srgb, var(--ainpa-admin-accent, #faf7fc) 5%, #fff);padding:8px 14px;font-size:12px;font-weight:800;color:var(--ainpa-admin-accent, #5b2e86);cursor:pointer}
        .ct-tab.is-active{background:var(--ainpa-admin-accent, #5b2e86);border-color:var(--ainpa-admin-accent, #5b2e86);color:#fff}
        .ct-panel{display:none}
        .ct-panel.is-active{display:block}
        .ct-tokens{max-height:520px;overflow:auto;border:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #e6d7ea) 14%, #fff);border-radius:10px}
        .ct-tokens h3{margin:0;padding:10px 12px;background:color-mix(in srgb, var(--ainpa-admin-accent, #faf7fc) 5%, #fff);border-bottom:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #efe6f2) 10%, #fff);font-size:12px;color:var(--ainpa-admin-accent, #5b2e86)}
        .ct-token{display:flex;gap:10px;align-items:flex-start;justify-content:space-between;padding:9px 12px;border-bottom:1px solid #f4eef7}
        .ct-token code{background:color-mix(in srgb, var(--ainpa-admin-accent, #f5edfb) 8%, #fff);border-radius:6px;padding:2px 7px;color:#4c1d95;font-size:12px;cursor:pointer;white-space:nowrap}
        .ct-token code.is-copied{background:#e7f6ec;color:#166534}
        .ct-token span{color:var(--ainpa-admin-muted, #6b7280);font-size:11.5px;line-height:1.6}
        .ct-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:16px;border-top:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #efe6f2) 10%, #fff);padding-top:16px}
        @media(max-width:900px){.ct-editor__grid{grid-template-columns:1fr}.ct-row{grid-template-columns:1fr}}
    </style>

    <main class="ct-editor" dir="ltr">
        <header class="ct-editor__head">
            <div>
                <h1>{{ __('certificates::ui.templates_title') }}</h1>
                <p>{{ __('certificates::ui.templates_intro') }}</p>
            </div>
            <div style="display:flex;gap:8px">
                <a class="ct-editor__btn is-light" href="{{ route('certificates.search') }}" target="_blank" rel="noopener noreferrer">{{ __('certificates::ui.preview_search') }}</a>
                <a class="ct-editor__btn" href="{{ route('admin.plugins.certificates.templates.index', [], false) }}">{{ __('certificates::ui.new_template') }}</a>
            </div>
        </header>

        @if(session('status'))<div class="ct-editor__notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="ct-editor__notice is-error">{{ $errors->first() }}</div>@endif

        <div class="ct-editor__grid">
            <aside class="ct-editor__card">
                <div class="ct-editor__card-head">Saved templates ({{ $templates->total() }})</div>
                <div class="ct-editor__list">
                    @forelse($templates as $item)
                        <a class="ct-tpl {{ $template->exists && $template->id === $item->id ? 'is-active' : '' }}" href="{{ route('admin.plugins.certificates.templates.edit', $item, false) }}">
                            <strong>{{ $item->name }}</strong>
                            <small>{{ $item->type === 'show' ? 'Certificate view' : 'Certificate search' }} · {{ ucfirst($item->status) }}</small>
                            @if($item->isSystem())<b>{{ __('certificates::ui.default_protected') }}</b>@endif
                            @if(($selectedIds[$item->type] ?? null) === $item->id)<b class="is-default">{{ __('certificates::ui.site_default') }}</b>@endif
                        </a>
                    @empty
                        <div class="ct-tpl"><strong>{{ __('certificates::ui.no_templates_yet') }}</strong></div>
                    @endforelse
                </div>
            </aside>

            <section class="ct-editor__card">
                <div class="ct-editor__card-head">{{ $template->exists ? 'Edit template' : 'New template' }}</div>
                <form class="ct-form" method="POST" action="{{ $template->exists ? route('admin.plugins.certificates.templates.update', $template, false) : route('admin.plugins.certificates.templates.store', [], false) }}">
                    @csrf
                    @if($template->exists) @method('PUT') @endif

                    <div class="ct-row">
                        <div class="ct-field">
                            <label for="template-name">{{ __('certificates::ui.name') }}</label>
                            <input id="template-name" name="name" required maxlength="255" value="{{ old('name', $template->name) }}">
                        </div>
                        <div class="ct-field">
                            <label for="template-slug">{{ __('certificates::ui.slug') }}</label>
                            <input id="template-slug" name="slug" maxlength="160" value="{{ old('slug', $template->slug) }}" placeholder="{{ __('certificates::ui.auto_generated') }}" @readonly($template->isSystem())>
                        </div>
                        <div class="ct-field">
                            <label for="template-type">{{ __('certificates::ui.template_use') }}</label>
                            <select id="template-type" name="type" @disabled($template->isSystem())>
                                <option value="search" @selected(old('type', $template->type) === 'search')>{{ __('certificates::ui.template_search_page') }}</option>
                                <option value="show" @selected(old('type', $template->type) === 'show')>{{ __('certificates::ui.template_show_page') }}</option>
                            </select>
                        </div>
                        <div class="ct-field">
                            <label for="template-status">{{ __('certificates::ui.status') }}</label>
                            <select id="template-status" name="status" @disabled($template->isSystem())>
                                <option value="active" @selected(old('status', $template->status) === 'active')>{{ __('certificates::ui.active') }}</option>
                                <option value="draft" @selected(old('status', $template->status) === 'draft')>{{ __('certificates::ui.draft') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="ct-tabs">
                        <button type="button" class="ct-tab is-active" data-tab="html">{{ __('certificates::ui.html') }}</button>
                        <button type="button" class="ct-tab" data-tab="css">{{ __('certificates::ui.css') }}</button>
                        <button type="button" class="ct-tab" data-tab="js">{{ __('certificates::ui.javascript') }}</button>
                        <button type="button" class="ct-tab" data-tab="vars">{{ __('certificates::ui.variables') }}</button>
                    </div>

                    <div class="ct-panel is-active" data-panel="html">
                        <div class="ct-field"><textarea name="html_code" spellcheck="false">{{ old('html_code', $template->html_code) }}</textarea></div>
                    </div>
                    <div class="ct-panel" data-panel="css">
                        <div class="ct-field"><textarea name="css_code" spellcheck="false">{{ old('css_code', $template->css_code) }}</textarea></div>
                    </div>
                    <div class="ct-panel" data-panel="js">
                        <div class="ct-field"><textarea name="js_code" spellcheck="false">{{ old('js_code', $template->js_code) }}</textarea></div>
                    </div>
                    <div class="ct-panel" data-panel="vars">
                        <div class="ct-tokens">
                            @foreach($tokenGroups as $group => $tokens)
                                <h3>{{ $group }}</h3>
                                @foreach($tokens as $token)
                                    <div class="ct-token">
                                        <code data-copy="{{ $token['token'] }}">{{ $token['token'] }}</code>
                                        <span>{{ $token['description'] }}</span>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>

                    <div class="ct-actions">
                        <button type="submit" class="ct-editor__btn">{{ __('certificates::ui.save') }}</button>
                        @if($template->exists)
                            <button type="submit" class="ct-editor__btn is-light" formaction="{{ route('admin.plugins.certificates.templates.default', $template, false) }}" formmethod="POST" name="_action" value="default">{{ __('certificates::ui.set_as_default') }}</button>
                            <button type="submit" class="ct-editor__btn is-light" formaction="{{ route('admin.plugins.certificates.templates.duplicate', $template, false) }}" formmethod="POST" name="_action" value="duplicate">{{ __('certificates::ui.copy') }}</button>
                        @endif
                    </div>
                </form>

                @if($template->exists)
                    <div class="ct-form" style="border-top:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #efe6f2) 10%, #fff)">
                        @if($template->isSystem())
                            <p style="margin:0;color:var(--ainpa-admin-muted, #6b7280);font-size:12px">{{ __('certificates::ui.templates_protected_hint') }}</p>
                        @else
                            <form method="POST" action="{{ route('admin.plugins.certificates.templates.destroy', $template, false) }}" onsubmit="return confirm(@js(__('certificates::ui.confirm_delete_template')));">
                                @csrf @method('DELETE')
                                <button type="submit" class="ct-editor__btn is-danger">{{ __('certificates::ui.delete_template') }}</button>
                            </form>
                        @endif
                    </div>
                @endif
            </section>
        </div>

        <div style="margin-top:16px">{{ $templates->links() }}</div>
    </main>

    <script>
        (function () {
            var tabs = document.querySelectorAll('[data-tab]');
            var panels = document.querySelectorAll('[data-panel]');
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tabs.forEach(function (t) { t.classList.remove('is-active'); });
                    panels.forEach(function (p) { p.classList.remove('is-active'); });
                    tab.classList.add('is-active');
                    var panel = document.querySelector('[data-panel="' + tab.getAttribute('data-tab') + '"]');
                    if (panel) { panel.classList.add('is-active'); }
                });
            });
            document.querySelectorAll('[data-copy]').forEach(function (code) {
                code.addEventListener('click', function () {
                    var text = code.getAttribute('data-copy');
                    if (navigator.clipboard && text) { navigator.clipboard.writeText(text); }
                    code.classList.add('is-copied');
                    window.setTimeout(function () { code.classList.remove('is-copied'); }, 900);
                });
            });
        })();
    </script>
</x-app-layout>
