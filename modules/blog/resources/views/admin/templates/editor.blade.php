<x-app-layout>
    @php
        $isEdit = $template->exists;
        $isSystem = (bool) ($isSystem ?? false);
        $action = $isEdit
            ? route('admin.plugins.blog.templates.update', $template, false)
            : route('admin.plugins.blog.templates.store', [], false);
        $previewImage = old('preview_image', $template->previewImageUrl());
        $allPostsExampleHtml = <<<'HTML'
<section class="posts-archive">
    <header class="posts-archive__header"><span>Latest stories</span><h1>All Posts</h1></header>
    <div class="posts-grid">
        @{{#posts}}
        <article class="post-card">
            <a class="post-card__image" href="@{{url}}"><img src="@{{featured_image}}" alt="@{{title}}"></a>
            <div class="post-card__body">
                <span class="post-card__category">@{{category}}</span>
                <h2><a href="@{{url}}">@{{title}}</a></h2>
                <p>@{{excerpt}}</p>
                <footer><span>@{{author}}</span><time>@{{published_at}}</time></footer>
            </div>
        </article>
        @{{/posts}}
    </div>
</section>
HTML;
        $allPostsExampleCss = <<<'CSS'
.posts-archive { max-width: 1180px; margin: 0 auto; padding: 48px 24px; font-family: Arial, sans-serif; color: #261d1d; }
.posts-archive__header { margin-bottom: 28px; text-align: center; }
.posts-archive__header span { color: #a90000; font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.posts-archive__header h1 { margin: 8px 0 0; font-size: clamp(32px, 5vw, 54px); }
.posts-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 22px; }
.post-card { overflow: hidden; border: 1px solid #eadada; border-radius: 14px; background: #fff; box-shadow: 0 12px 30px rgba(57, 24, 24, .08); }
.post-card__image { display: block; overflow: hidden; aspect-ratio: 16 / 10; background: #f2e5e5; }
.post-card__image img { width: 100%; height: 100%; display: block; object-fit: cover; transition: transform .25s ease; }
.post-card:hover .post-card__image img { transform: scale(1.035); }
.post-card__body { padding: 20px; }
.post-card__category { color: #a90000; font-size: 11px; font-weight: 800; text-transform: uppercase; }
.post-card h2 { margin: 8px 0 10px; font-size: 22px; line-height: 1.25; }
.post-card h2 a { color: inherit; text-decoration: none; }
.post-card p { margin: 0; color: #6b5b5b; font-size: 14px; line-height: 1.65; }
.post-card footer { display: flex; justify-content: space-between; gap: 10px; margin-top: 18px; padding-top: 14px; border-top: 1px solid #f0e2e2; color: #897676; font-size: 11px; }
@media (max-width: 850px) { .posts-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) { .posts-archive { padding: 30px 14px; } .posts-grid { grid-template-columns: 1fr; } }
CSS;
    @endphp

    <style>
        .template-studio{width:100%;padding:20px 24px 36px;color:#211b1b;background:#fffafa}.studio-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px}.studio-head h1{margin:0;font-size:24px;font-weight:850}.studio-head p{margin:4px 0 0;color:#756767;font-size:12px}.studio-button{display:inline-flex;min-height:36px;align-items:center;justify-content:center;border:1px solid transparent;border-radius:7px;background:#a90000;padding:0 14px;color:#fff;font-size:12px;font-weight:800;text-decoration:none;cursor:pointer}.studio-button.secondary{border-color:#dfbcbc;background:#fff;color:#8b0000}.studio-button.danger{border-color:#efb5b5;background:#fff;color:#b91c1c}.studio-button:disabled{opacity:.45;cursor:not-allowed}.studio-layout{display:grid;grid-template-columns:230px minmax(0,1fr);gap:14px;align-items:start}.studio-library{position:sticky;top:14px;overflow:hidden;border:1px solid #ead2d2;border-radius:10px;background:#fff;box-shadow:0 5px 18px rgba(90,25,25,.05)}.studio-library-head{display:flex;align-items:center;justify-content:space-between;padding:11px 12px;border-bottom:1px solid #f0dddd}.studio-library-head strong{font-size:13px}.studio-template-list{display:grid;gap:5px;max-height:calc(100vh - 190px);overflow:auto;padding:8px}.studio-template-item{display:grid;grid-template-columns:42px minmax(0,1fr);gap:8px;align-items:center;border:1px solid transparent;border-radius:7px;padding:6px;color:#332727;text-decoration:none}.studio-template-item:hover,.studio-template-item.active{border-color:#e8c3c3;background:#fff3f3}.studio-template-item img,.studio-template-thumb{width:42px;height:42px;border-radius:6px;background:#f5e4e4;object-fit:cover}.studio-template-thumb{display:grid;place-items:center;color:#9f1239;font-size:15px;font-weight:900}.studio-template-item strong{display:block;overflow:hidden;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.studio-template-item small{display:block;margin-top:2px;color:#8b7777;font-size:9px}.studio-workspace{min-width:0}.studio-section{overflow:hidden;margin-bottom:12px;border:1px solid #ead2d2;border-radius:10px;background:#fff;box-shadow:0 5px 18px rgba(90,25,25,.04)}.studio-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:43px;padding:9px 13px;border-bottom:1px solid #f0dddd;background:#fffdfd}.studio-section-head h2{margin:0;font-size:13px;font-weight:850}.studio-section-head span{color:#8a7474;font-size:10px}.studio-code-actions{display:flex;align-items:center;gap:8px}.studio-meta{display:grid;grid-template-columns:minmax(220px,1.4fr) minmax(150px,.8fr) 125px 190px;gap:10px;padding:13px}.studio-field{display:grid;gap:5px;min-width:0}.studio-field label{color:#5b4747;font-size:10px;font-weight:800}.studio-input,.studio-select{width:100%;min-height:37px;border:1px solid #dcbcbc;border-radius:7px;background:#fff;padding:7px 9px;color:#241b1b;font-size:12px}.studio-image-field{display:flex;align-items:center;gap:7px}.studio-image-preview{width:45px;height:37px;border:1px solid #e4caca;border-radius:6px;background:#fff4f4;object-fit:cover}.studio-code-tabs{display:flex;gap:3px;padding:8px 10px 0;border-bottom:1px solid #f0dddd;background:#fffafa}.studio-code-tab{min-height:34px;border:1px solid transparent;border-bottom:0;border-radius:6px 6px 0 0;background:transparent;padding:0 13px;color:#715858;font-size:11px;font-weight:800;cursor:pointer}.studio-code-tab.active{border-color:#e4c4c4;background:#fff;color:#a90000}.studio-code-panel{display:none}.studio-code-panel.active{display:block}.studio-code{display:block;width:100%;height:360px;resize:vertical;border:0;background:#16181d;padding:16px;color:#f3f4f6;font:13px/1.65 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;tab-size:4;outline:0}.studio-code:focus{box-shadow:inset 0 0 0 2px #d92d20}.studio-code-status{display:flex;justify-content:space-between;padding:7px 11px;background:#24272e;color:#aeb6c2;font:10px ui-monospace,monospace}.studio-preview-tools{display:flex;align-items:center;gap:5px}.studio-preview-size{min-height:29px;border:1px solid #dec2c2;border-radius:6px;background:#fff;padding:0 9px;color:#6f5555;font-size:10px;font-weight:800;cursor:pointer}.studio-preview-size.active{border-color:#b91c1c;color:#a90000}.studio-preview-stage{min-height:320px;overflow:visible;background:#ece8e8;padding:18px}.studio-preview-frame{display:block;width:100%;height:320px;overflow:hidden;margin:0 auto;border:1px solid #d1c6c6;border-radius:8px;background:#fff;box-shadow:0 10px 30px rgba(35,20,20,.12);transition:width .2s,height .16s}.studio-preview-frame.tablet{width:min(768px,100%)}.studio-preview-frame.mobile{width:min(390px,100%)}.studio-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 13px}.studio-actions>div{display:flex;gap:7px}.studio-notice,.studio-errors{margin-bottom:11px;border-left:4px solid #15803d;border-radius:6px;background:#ecfdf5;padding:9px 12px;color:#166534;font-size:12px}.studio-errors{border-color:#dc2626;background:#fff1f2;color:#991b1b}.media-modal{position:fixed;inset:0;z-index:1200;display:none;align-items:center;justify-content:center;background:rgba(20,12,12,.62)}.media-modal.open{display:flex}.media-panel{display:grid;grid-template-rows:auto auto 1fr auto;width:min(980px,92vw);max-height:88vh;border:1px solid #2d2020;background:#fff}.media-head,.media-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 15px;border-bottom:1px solid #e7d4d4}.media-foot{border-top:1px solid #e7d4d4;border-bottom:0}.media-tabs{display:flex;border-bottom:1px solid #e7d4d4;background:#faf5f5}.media-tab{border:0;border-right:1px solid #e7d4d4;background:transparent;padding:10px 14px;cursor:pointer}.media-tab.active{background:#fff;color:#a90000;font-weight:800}.media-body{min-height:410px;overflow:auto;padding:16px}.media-view[hidden]{display:none}.media-drop{display:flex;min-height:290px;align-items:center;justify-content:center;flex-direction:column;gap:10px;border:2px dashed #d6b7b7;background:#fffafa}.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(115px,1fr));gap:9px}.media-item{overflow:hidden;border:2px solid transparent;background:#f7f1f1;padding:5px;cursor:pointer}.media-item.selected{border-color:#a90000}.media-item img{display:block;width:100%;height:88px;object-fit:cover}.media-item small{display:block;overflow:hidden;margin-top:4px;text-overflow:ellipsis;white-space:nowrap}.media-selected{display:flex;align-items:center;gap:9px;min-width:0;color:#645555;font-size:11px}.media-selected img{width:46px;height:46px;object-fit:cover}.media-selected span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.studio-empty{padding:16px;text-align:center;color:#806d6d;font-size:11px}@media(max-width:1050px){.studio-layout{grid-template-columns:1fr}.studio-library{position:static}.studio-template-list{grid-template-columns:repeat(auto-fill,minmax(180px,1fr));max-height:none}.studio-meta{grid-template-columns:1fr 1fr}}@media(max-width:650px){.template-studio{padding:14px 9px 24px}.studio-head{align-items:flex-start;flex-direction:column}.studio-meta{grid-template-columns:1fr}.studio-preview-stage{padding:8px}.studio-actions{align-items:stretch;flex-direction:column}.studio-actions>div{width:100%}.studio-actions .studio-button{flex:1}}
    </style>
    <style>
        .studio-classes{min-height:360px;background:#fff;padding:16px}.studio-classes-note{margin:0 0 14px;border-left:4px solid #a90000;border-radius:6px;background:#fff4f4;padding:10px 12px;color:#664f4f;font-size:11px;line-height:1.55}.studio-token-groups{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.studio-token-group{overflow:hidden;border:1px solid #ead5d5;border-radius:8px;background:#fff}.studio-token-group h3{margin:0;border-bottom:1px solid #ecdada;background:#fff9f9;padding:9px 11px;font-size:12px}.studio-token-list{display:grid}.studio-token{display:grid;grid-template-columns:minmax(170px,.75fr) minmax(0,1fr);align-items:center;gap:10px;border:0;border-bottom:1px solid #f2e5e5;background:#fff;padding:8px 10px;text-align:left;cursor:pointer}.studio-token:last-child{border-bottom:0}.studio-token:hover{background:#fff3f3}.studio-token code{overflow:hidden;color:#a90000;font:700 11px/1.4 ui-monospace,monospace;text-overflow:ellipsis;white-space:nowrap}.studio-token span{color:#6f6060;font-size:10px;line-height:1.45}.studio-token-status{min-height:31px;border-top:1px solid #eadada;background:#24272e;padding:8px 12px;color:#d9dee7;font:10px ui-monospace,monospace}@media(max-width:800px){.studio-token-groups{grid-template-columns:1fr}.studio-token{grid-template-columns:1fr}}
    </style>

    <div class="template-studio">
        <div class="studio-head">
            <div><h1>Post Template Studio</h1><p>Create reusable Blog markup and CSS with an isolated live preview.</p></div>
            <a class="studio-button secondary" href="{{ route('admin.plugins.blog.templates.index', [], false) }}">New Template</a>
        </div>

        @if (session('status'))<div class="studio-notice">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="studio-errors"><strong>Template was not saved.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="studio-layout">
            <aside class="studio-library">
                <div class="studio-library-head"><strong>Saved templates</strong><span>{{ $templates->count() }}</span></div>
                <div class="studio-template-list">
                    @forelse($templates as $item)
                        <a class="studio-template-item @if($isEdit && $item->id === $template->id) active @endif" href="{{ route('admin.plugins.blog.templates.edit', $item, false) }}">
                            @if($item->previewImageUrl())<img src="{{ $item->previewImageUrl() }}" alt="">@else<div class="studio-template-thumb">T</div>@endif
                            <span><strong>{{ $item->name }}</strong><small>{{ $item->category }} · {{ $item->status }}{{ $item->isSystem() ? ' · Locked' : '' }}</small></span>
                        </a>
                    @empty
                        <div class="studio-empty">No templates saved yet.</div>
                    @endforelse
                </div>
            </aside>

            <form class="studio-workspace" method="POST" action="{{ $action }}" id="template-form">
                @csrf
                @if($isEdit) @method('PUT') @endif
                <input type="hidden" name="preview_image_id" id="preview-image-id" value="{{ old('preview_image_id', $template->preview_image_id) }}">
                <input type="hidden" name="preview_image" id="preview-image-url" value="{{ $previewImage }}">

                @if($isSystem)<div class="studio-notice"><strong>Protected default template.</strong> Its code and identity are read-only. Use Copy Template to create an editable version.</div>@endif

                <section class="studio-section">
                    <div class="studio-section-head"><h2>1. Template information</h2><span>Stable identity and Page Builder catalog data</span></div>
                    <div class="studio-meta">
                        <div class="studio-field"><label for="template-name">Template name</label><input class="studio-input" id="template-name" name="name" value="{{ old('name', $template->name) }}" placeholder="Example: Featured Post Card" required @readonly($isSystem)></div>
                        <div class="studio-field"><label for="template-slug">Slug</label><input class="studio-input" id="template-slug" name="slug" value="{{ old('slug', $template->slug) }}" placeholder="auto-generated" @readonly($isSystem)></div>
                        <div class="studio-field"><label for="template-category">Category</label><input class="studio-input" id="template-category" name="category" list="template-categories" value="{{ old('category', $template->category ?: 'custom') }}" required @readonly($isSystem)><datalist id="template-categories">@foreach($categories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</datalist></div>
                        <div class="studio-field"><label>Preview image</label><div class="studio-image-field"><img class="studio-image-preview" id="preview-image" src="{{ $previewImage ?: 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=' }}" alt=""><button class="studio-button secondary" id="choose-image" type="button" @disabled($isSystem)>Media</button></div></div>
                        <div class="studio-field"><label for="template-status">Status</label><select class="studio-select" id="template-status" name="status" @disabled($isSystem)><option value="active" @selected(old('status', $template->status ?: 'active') === 'active')>Active</option><option value="draft" @selected(old('status', $template->status) === 'draft')>Draft</option></select></div>
                    </div>
                </section>

                <section class="studio-section">
                    <div class="studio-section-head"><h2>2. Template code</h2><div class="studio-code-actions"><span>HTML structure and isolated CSS</span><button class="studio-button secondary" id="load-all-posts-example" type="button" @disabled($isSystem)>Load All Posts Example</button></div></div>
                    <div class="studio-code-tabs"><button class="studio-code-tab active" type="button" data-code-tab="html">HTML</button><button class="studio-code-tab" type="button" data-code-tab="css">CSS</button><button class="studio-code-tab" type="button" data-code-tab="js">JavaScript</button><button class="studio-code-tab" type="button" data-code-tab="classes">Classes You Can Use</button></div>
                    <div class="studio-code-panel active" data-code-panel="html"><textarea class="studio-code" id="html-code" name="html_code" spellcheck="false" @readonly($isSystem)>{{ old('html_code', $template->html_code) }}</textarea><div class="studio-code-status"><span>HTML</span><span id="html-lines">0 lines</span></div></div>
                    <div class="studio-code-panel" data-code-panel="css"><textarea class="studio-code" id="css-code" name="css_code" spellcheck="false" @readonly($isSystem)>{{ old('css_code', $template->css_code) }}</textarea><div class="studio-code-status"><span>CSS</span><span id="css-lines">0 lines</span></div></div>
                    <div class="studio-code-panel" data-code-panel="js"><textarea class="studio-code" id="js-code" name="js_code" spellcheck="false" @readonly($isSystem)>{{ old('js_code', $template->js_code) }}</textarea><div class="studio-code-status"><span>JavaScript · loaded as a separate frontend file</span><span id="js-lines">0 lines</span></div></div>
                    <div class="studio-code-panel" data-code-panel="classes">
                        <div class="studio-classes">
                            <p class="studio-classes-note"><strong>How to use:</strong> place a variable in the HTML tab. Collection markers repeat their inner markup. Click any variable below to copy it and insert it at the current HTML cursor position. CSS class names are yours to define; these variables provide the saved Blog data.</p>
                            <div class="studio-token-groups">
                                @foreach($tokenGroups as $group => $entries)
                                    <section class="studio-token-group">
                                        <h3>{{ $group }}</h3>
                                        <div class="studio-token-list">
                                            @foreach($entries as $entry)
                                                <button class="studio-token" type="button" data-template-token="{{ $entry['token'] }}" @disabled($isSystem)><code>{{ $entry['token'] }}</code><span>{{ $entry['description'] }}</span></button>
                                            @endforeach
                                        </div>
                                    </section>
                                @endforeach
                            </div>
                        </div>
                        <div class="studio-token-status" id="template-token-status">Select a variable to copy and insert it into HTML.</div>
                    </div>
                </section>

                <section class="studio-section">
                    <div class="studio-section-head"><h2>3. Live preview</h2><div class="studio-preview-tools"><button class="studio-preview-size active" type="button" data-preview-size="desktop">Desktop</button><button class="studio-preview-size" type="button" data-preview-size="tablet">Tablet</button><button class="studio-preview-size" type="button" data-preview-size="mobile">Mobile</button></div></div>
                    <div class="studio-preview-stage"><iframe class="studio-preview-frame" id="template-preview" title="Template live preview" scrolling="no" sandbox="allow-same-origin allow-scripts"></iframe></div>
                    <div class="studio-actions">
                        <div>@if($isEdit && !$isSystem)<button class="studio-button danger" type="submit" form="delete-template">Delete</button>@endif</div>
                        <div>@if($isSystem)<button class="studio-button secondary" type="submit" form="duplicate-template">Copy Template</button>@endif<button class="studio-button secondary" type="button" id="refresh-preview">Refresh preview</button>@if(!$isSystem)<button class="studio-button" type="submit">{{ $isEdit ? 'Save Template' : 'Create Template' }}</button>@endif</div>
                    </div>
                </section>
            </form>
        </div>
        @if($isEdit && !$isSystem)<form id="delete-template" method="POST" action="{{ route('admin.plugins.blog.templates.destroy', $template, false) }}" hidden>@csrf @method('DELETE')</form>@endif
        @if($isEdit && $isSystem)<form id="duplicate-template" method="POST" action="{{ route('admin.plugins.blog.templates.duplicate', $template, false) }}" hidden>@csrf</form>@endif
    </div>

    <div class="media-modal" id="media-modal" aria-hidden="true">
        <div class="media-panel">
            <div class="media-head"><strong>Media Library</strong><button class="studio-button secondary" type="button" id="close-media">Close</button></div>
            <div class="media-tabs"><button class="media-tab active" type="button" data-media-tab="upload">Upload files</button><button class="media-tab" type="button" data-media-tab="library">Media Library</button></div>
            <div class="media-body">
                <div class="media-view" data-media-view="upload"><div class="media-drop"><strong>Upload a template preview image</strong><button class="studio-button secondary" type="button" id="select-file">Select image</button><input id="media-file" type="file" accept="image/*" hidden><span id="upload-status"></span></div></div>
                <div class="media-view" data-media-view="library" hidden><div class="media-grid" id="media-grid"><p>Open the library to load images.</p></div></div>
            </div>
            <div class="media-foot"><div class="media-selected" id="media-selected">Select or upload an image.</div><button class="studio-button" id="use-media" type="button" disabled>Use image</button></div>
        </div>
    </div>

    <script>
        (() => {
            const html = document.getElementById('html-code');
            const css = document.getElementById('css-code');
            const js = document.getElementById('js-code');
            const preview = document.getElementById('template-preview');
            const csrf = @json(csrf_token());
            const mediaIndex = @json(route('admin.media.index', [], false));
            const mediaStore = @json(route('admin.media.store', [], false));
            const allPostsExampleHtml = @json($allPostsExampleHtml);
            const allPostsExampleCss = @json($allPostsExampleCss);
            const isSystemTemplate = @json($isSystem);
            const samplePosts = [
                {title:'The language of contemporary art',excerpt:'A concise preview of the article appears here for visitors browsing the archive.',category:'Art',author:'Art Z',published_at:'August 5, 2026',url:'#post-1',featured_image:'https://placehold.co/900x560/f1dfdf/7f1d1d?text=Art+Story'},
                {title:'Inside the artist studio',excerpt:'Use the same template for archives, categories, search results, and reusable grids.',category:'Artists',author:'Art Z',published_at:'August 4, 2026',url:'#post-2',featured_image:'https://placehold.co/900x560/e9e1da/563b2d?text=Artist+Studio'},
                {title:'Exhibitions worth discovering',excerpt:'Every item is generated from the content written between the posts loop markers.',category:'Exhibitions',author:'Art Z',published_at:'August 3, 2026',url:'#post-3',featured_image:'https://placehold.co/900x560/e2e7e8/334b54?text=Exhibition'},
                {title:'A new perspective on sculpture',excerpt:'Responsive CSS in the style tab is applied immediately to this preview.',category:'Sculpture',author:'Art Z',published_at:'August 2, 2026',url:'#post-4',featured_image:'https://placehold.co/900x560/eee5d8/765e3f?text=Sculpture'},
                {title:'Collecting art with confidence',excerpt:'Change any class, layout, type size, color, spacing, or card behavior.',category:'Collecting',author:'Art Z',published_at:'August 1, 2026',url:'#post-5',featured_image:'https://placehold.co/900x560/eadfe7/6b405e?text=Collection'},
                {title:'Weekly culture highlights',excerpt:'The production renderer uses the same tokens with real published Blog posts.',category:'Culture',author:'Art Z',published_at:'July 31, 2026',url:'#post-6',featured_image:'https://placehold.co/900x560/e0e6dc/48613e?text=Culture'}
            ].map((post, index) => ({
                id:String(index + 1),slug:`sample-post-${index + 1}`,content:`<p>${post.excerpt}</p>`,content_text:post.excerpt,featured_image_alt:post.title,
                category_slug:'art',category_url:'#category',categories:'Art, Culture',tags:'art, culture',author_id:'1',published_at_iso:'2026-08-05T12:00:00Z',created_at:post.published_at,updated_at:post.published_at,
                status:'published',visibility:'public',template:'default',layout:'default',seo_title:post.title,seo_description:post.excerpt,focus_keyword:'art',canonical_url:post.url,
                robots_index:'index',robots_follow:'follow',schema_type:'BlogPosting',seo_score:'92',seo_social_title:post.title,seo_social_description:post.excerpt,
                tag_items:[{name:'art',slug:'art',url:'#tag-art'},{name:'culture',slug:'culture',url:'#tag-culture'}],
                category_items:[{name:post.category,slug:'art',url:'#category'}],
                ...post
            }));
            let timer;
            let selectedMedia = null;
            let mediaLoaded = false;
            let previewObserver = null;

            function replaceTokens(markup, values) {
                const withRawContent = markup.replace(/\{\{\{\s*content\s*\}\}\}/gi, values.content || '');
                return withRawContent.replace(/\{\{\s*([a-z_]+)\s*\}\}/gi, (match, key) => Object.hasOwn(values, key) ? values[key] : match);
            }

            function renderSamplePost(markup, post) {
                const withTags = markup.replace(/\{\{#tags\}\}([\s\S]*?)\{\{\/tags\}\}/gi, (match, itemMarkup) => post.tag_items.map(tag => replaceTokens(itemMarkup, tag)).join(''));
                const withCategories = withTags.replace(/\{\{#categories\}\}([\s\S]*?)\{\{\/categories\}\}/gi, (match, itemMarkup) => post.category_items.map(category => replaceTokens(itemMarkup, category)).join(''));
                return replaceTokens(withCategories, post);
            }

            function previewMarkup(markup) {
                const expanded = markup.replace(/\{\{#posts\}\}([\s\S]*?)\{\{\/posts\}\}/gi, (match, itemMarkup) => samplePosts.map(post => renderSamplePost(itemMarkup, post)).join(''));
                return replaceTokens(expanded, {site_name:'Art Z', archive_title:'All Posts', results_count:String(samplePosts.length)});
            }

            function syncPreviewHeight() {
                const documentNode = preview.contentDocument;
                if (!documentNode?.documentElement || !documentNode.body) return;
                const naturalHeight = Math.max(documentNode.body.scrollHeight, documentNode.body.offsetHeight, documentNode.documentElement.scrollHeight, documentNode.documentElement.offsetHeight);
                preview.style.height = `${Math.min(2000, Math.max(320, naturalHeight + 2))}px`;
                if (previewObserver) previewObserver.disconnect();
                previewObserver = new ResizeObserver(() => {
                    const height = Math.max(documentNode.body.scrollHeight, documentNode.documentElement.scrollHeight);
                    preview.style.height = `${Math.min(2000, Math.max(320, height + 2))}px`;
                });
                previewObserver.observe(documentNode.body);
            }

            function render() {
                clearTimeout(timer);
                const safeJs = js.value.split('</scr' + 'ipt').join('<\\/scr' + 'ipt');
                const runtimeScript = safeJs ? '<scr' + 'ipt>' + safeJs + '</scr' + 'ipt>' : '';
                const documentHtml = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html{background:#fff;overflow:hidden}body{margin:0;padding:1px;overflow:hidden}${css.value}</style></head><body><div data-blog-template="preview">${previewMarkup(html.value)}</div>${runtimeScript}</body></html>`;
                preview.srcdoc = documentHtml;
                document.getElementById('html-lines').textContent = `${html.value.split(/\n/).length} lines`;
                document.getElementById('css-lines').textContent = `${css.value.split(/\n/).length} lines`;
                document.getElementById('js-lines').textContent = `${js.value.split(/\n/).length} lines`;
            }

            function queueRender() { clearTimeout(timer); timer = setTimeout(render, 120); }
            [html, css, js].forEach(editor => {
                editor.addEventListener('input', queueRender);
                editor.addEventListener('keydown', event => {
                    if (isSystemTemplate) return;
                    if (event.key !== 'Tab') return;
                    event.preventDefault();
                    const start = editor.selectionStart;
                    editor.setRangeText('    ', start, editor.selectionEnd, 'end');
                    queueRender();
                });
            });

            document.querySelectorAll('[data-code-tab]').forEach(button => button.addEventListener('click', () => {
                document.querySelectorAll('[data-code-tab]').forEach(item => item.classList.toggle('active', item === button));
                document.querySelectorAll('[data-code-panel]').forEach(panel => panel.classList.toggle('active', panel.dataset.codePanel === button.dataset.codeTab));
            }));
            document.querySelectorAll('[data-template-token]').forEach(button => button.addEventListener('click', async () => {
                if (isSystemTemplate) return;
                const token = button.dataset.templateToken;
                const insertion = token.includes('...') ? token.replace('...', '\n    \n') : token;
                const start = Number.isInteger(html.selectionStart) ? html.selectionStart : html.value.length;
                html.setRangeText(insertion, start, html.selectionEnd ?? start, 'end');
                const status = document.getElementById('template-token-status');
                try { await navigator.clipboard.writeText(token); status.textContent = `${token} copied and inserted into HTML.`; }
                catch (error) { status.textContent = `${token} inserted into HTML.`; }
                queueRender();
            }));
            document.querySelectorAll('[data-preview-size]').forEach(button => button.addEventListener('click', () => {
                document.querySelectorAll('[data-preview-size]').forEach(item => item.classList.toggle('active', item === button));
                preview.classList.remove('tablet', 'mobile');
                if (button.dataset.previewSize !== 'desktop') preview.classList.add(button.dataset.previewSize);
            }));
            document.getElementById('refresh-preview').addEventListener('click', render);
            preview.addEventListener('load', () => { syncPreviewHeight(); setTimeout(syncPreviewHeight, 180); setTimeout(syncPreviewHeight, 700); });
            document.getElementById('load-all-posts-example').addEventListener('click', () => {
                if (isSystemTemplate) return;
                if ((html.value.trim() || css.value.trim()) && !window.confirm('Replace the current HTML and CSS with the All Posts example?')) return;
                html.value = allPostsExampleHtml;
                css.value = allPostsExampleCss;
                js.value = '';
                document.getElementById('template-category').value = 'archive';
                render();
            });

            const modal = document.getElementById('media-modal');
            function openMedia() { modal.classList.add('open'); modal.setAttribute('aria-hidden', 'false'); activateMedia('library'); }
            function closeMedia() { modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); }
            function activateMedia(name) {
                document.querySelectorAll('[data-media-tab]').forEach(tab => tab.classList.toggle('active', tab.dataset.mediaTab === name));
                document.querySelectorAll('[data-media-view]').forEach(view => view.hidden = view.dataset.mediaView !== name);
                if (name === 'library') loadMedia();
            }
            function selectMedia(item, button) {
                document.querySelectorAll('.media-item').forEach(tile => tile.classList.remove('selected'));
                if (button) button.classList.add('selected');
                selectedMedia = item;
                const selected = document.getElementById('media-selected');
                selected.replaceChildren();
                const image = document.createElement('img'); image.src = item.url; image.alt = '';
                const label = document.createElement('span'); label.textContent = item.title || item.name || item.url;
                selected.append(image, label);
                document.getElementById('use-media').disabled = false;
            }
            function mediaTile(item) {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'media-item';
                const image = document.createElement('img'); image.src = item.url; image.alt = item.alt_text || '';
                const label = document.createElement('small'); label.textContent = item.title || item.name || 'Image';
                button.append(image, label); button.addEventListener('click', () => selectMedia(item, button));
                return button;
            }
            async function loadMedia(force = false) {
                if (mediaLoaded && !force) return;
                const grid = document.getElementById('media-grid'); grid.textContent = 'Loading media…';
                try {
                    const response = await fetch(mediaIndex, {headers:{Accept:'application/json'}});
                    if (!response.ok) throw new Error('Media library could not be loaded.');
                    const data = await response.json(); grid.replaceChildren();
                    (data.items || []).filter(item => item.url).forEach(item => grid.append(mediaTile(item)));
                    if (!grid.children.length) grid.textContent = 'No images found.';
                    mediaLoaded = true;
                } catch (error) { grid.textContent = error.message; }
            }
            async function upload() {
                const file = document.getElementById('media-file').files[0]; if (!file) return;
                const status = document.getElementById('upload-status'); status.textContent = 'Uploading…';
                const body = new FormData(); body.append('image', file); body.append('title', file.name.replace(/\.[^.]+$/, ''));
                try {
                    const response = await fetch(mediaStore, {method:'POST',headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'},body});
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Upload failed.');
                    mediaLoaded = false; status.textContent = 'Uploaded successfully.'; await loadMedia(true); activateMedia('library'); selectMedia(data.media, null);
                } catch (error) { status.textContent = error.message; }
            }
            document.getElementById('choose-image').addEventListener('click', openMedia);
            document.getElementById('close-media').addEventListener('click', closeMedia);
            document.querySelectorAll('[data-media-tab]').forEach(tab => tab.addEventListener('click', () => activateMedia(tab.dataset.mediaTab)));
            document.getElementById('select-file').addEventListener('click', () => document.getElementById('media-file').click());
            document.getElementById('media-file').addEventListener('change', upload);
            document.getElementById('use-media').addEventListener('click', () => {
                if (!selectedMedia) return;
                document.getElementById('preview-image-id').value = '';
                document.getElementById('preview-image-url').value = selectedMedia.url;
                document.getElementById('preview-image').src = selectedMedia.url;
                closeMedia();
            });
            modal.addEventListener('click', event => { if (event.target === modal) closeMedia(); });
            render();
        })();
    </script>
</x-app-layout>
