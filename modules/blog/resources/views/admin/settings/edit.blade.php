<x-app-layout>
    <style>
        .blog-settings{max-width:1040px;margin:0 auto;padding:28px 22px}.blog-settings__head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px}.blog-settings__head h1{margin:0;font-size:25px}.blog-settings__head p{margin:6px 0 0;color:#786767;font-size:13px}.blog-settings__link,.blog-settings button{display:inline-flex;min-height:38px;align-items:center;justify-content:center;border:1px solid #dcbaba;border-radius:8px;background:#fff;padding:0 15px;color:#8b0000;font-size:12px;font-weight:800;text-decoration:none;cursor:pointer}.blog-settings button{border-color:#a90000;background:#a90000;color:#fff}.blog-settings__card{overflow:hidden;border:1px solid #ead2d2;border-radius:12px;background:#fff;box-shadow:0 8px 26px rgba(80,20,20,.06)}.blog-settings__intro{border-bottom:1px solid #eedddd;background:#fff8f8;padding:18px 20px}.blog-settings__intro h2{margin:0;font-size:16px}.blog-settings__intro p{margin:5px 0 0;color:#756565;font-size:12px}.blog-settings__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;padding:20px}.blog-settings__field{display:grid;gap:7px;border:1px solid #eee1e1;border-radius:9px;padding:14px}.blog-settings__field label{font-size:13px;font-weight:850}.blog-settings__field small{color:#847373;font-size:11px}.blog-settings__field select{width:100%;min-height:42px;border:1px solid #dabdbd;border-radius:7px;background:#fff;padding:8px 10px;color:#2c2222}.blog-settings__system{color:#9b111e;font-size:10px;font-weight:800}.blog-settings__actions{display:flex;justify-content:flex-end;border-top:1px solid #eedddd;background:#fffafa;padding:14px 20px}.blog-settings__status{margin-bottom:14px;border-left:4px solid #15803d;background:#ecfdf5;padding:10px 13px;color:#166534}.blog-settings__errors{margin-bottom:14px;border-left:4px solid #dc2626;background:#fff1f2;padding:10px 13px;color:#991b1b}@media(max-width:700px){.blog-settings__grid{grid-template-columns:1fr}.blog-settings__head{flex-direction:column}}
    </style>
    <div class="blog-settings">
        <div class="blog-settings__head"><div><h1>Blog Settings</h1><p>Select the active site-wide template for each Blog page type.</p></div><a class="blog-settings__link" href="{{ route('admin.plugins.blog.templates.index', [], false) }}">Manage Templates</a></div>
        @if(session('status'))<div class="blog-settings__status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="blog-settings__errors">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('admin.plugins.blog.settings.update', [], false) }}" class="blog-settings__card">@csrf @method('PATCH')
            <div class="blog-settings__intro"><h2>Default frontend templates</h2><p>Locked defaults are always available. Active custom copies appear after you save them with the matching template category.</p></div>
            <div class="blog-settings__grid">
                @foreach($contexts as $context => $label)
                    <div class="blog-settings__field"><label for="setting-{{ $context }}">{{ $label }}</label><small>Used globally for {{ strtolower($label) }} pages.</small>
                        <select id="setting-{{ $context }}" name="{{ $context }}" required>
                            @foreach(($templates[$context] ?? collect()) as $template)
                                <option value="{{ $template->id }}" @selected((int)old($context, $selections[$context] ?? 0) === $template->id)>{{ $template->name }}{{ $template->isSystem() ? ' · Locked default' : '' }}</option>
                            @endforeach
                        </select>
                        @if(($templates[$context] ?? collect())->contains(fn($template) => $template->isSystem()))<span class="blog-settings__system">A protected system fallback is available.</span>@endif
                    </div>
                @endforeach
            </div>
            <div class="blog-settings__actions"><button type="submit">Save Blog Settings</button></div>
        </form>
    </div>
</x-app-layout>
