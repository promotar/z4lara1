<x-app-layout>
    @php
        $isEdit = $post->exists;
        $action = $isEdit ? route('admin.plugins.blog.posts.update', $post, false) : route('admin.plugins.blog.posts.store', [], false);
        $selectedTemplate = old('template', $post->template ?: $post->layout_template ?: 'default');
        $selectedFeaturedId = (int) old('featured_image_id', $post->featured_image_id);
        $featuredUrl = old('featured_image', $post->featuredImage?->url ?: $post->featured_image);
        $featuredAlt = old('featured_image_alt', $post->featured_image_alt ?: $post->featuredImage?->alt_text);
        $hasError = fn (string $field): bool => isset($errors) && $errors->has($field);
        $errorClass = fn (string $field): string => $hasError($field) ? ' wp-field-error' : '';
        $errorId = fn (string $field): string => 'error-'.str_replace(['.', '_'], '-', $field);
        $errorLabels = [
            'title' => 'Title',
            'slug' => 'Slug',
            'content' => 'Content',
            'excerpt' => 'Excerpt',
            'status' => 'Status',
            'visibility' => 'Visibility',
            'password' => 'Password',
            'published_at' => 'Publish date',
            'scheduled_at' => 'Schedule date',
            'category_id' => 'Category',
            'featured_image_id' => 'Featured image',
            'featured_image_alt' => 'Featured image alt',
            'template' => 'Template',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO meta description',
            'focus_keyword' => 'Focus keyword',
            'canonical_url' => 'Canonical URL',
            'schema_type' => 'Schema type',
        ];
        $publicPostPath = $isEdit && $post->slug ? '/blog/'.$post->slug : null;
        $isPubliclyViewable = $publicPostPath
            && $post->status === 'published'
            && $post->visibility === 'public'
            && (! $post->published_at || $post->published_at->isPast())
            && (! $post->scheduled_at || $post->scheduled_at->isPast());
        $selectedTags = collect(explode(',', (string) old('tags', $isEdit ? $post->tags->pluck('name')->implode(',') : '')))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique(fn ($tag) => mb_strtolower($tag))
            ->values();
    @endphp

    <style>
        body { background: #f0f0f1; }
        .wp-post-screen { background:#f0f0f1; color:#1d2327; font-family: Arial, sans-serif; padding: 18px 22px 32px; }
        .wp-post-screen * { box-sizing: border-box; }
        .wp-heading { font-size:23px; font-weight:400; margin:0 0 10px; }
        .wp-notice { background:#fff; border-left:4px solid #00a32a; box-shadow:0 1px 1px rgba(0,0,0,.04); margin:0 0 14px; padding:10px 12px; }
        .wp-notice.error { border-left-color:#d63638; }
        .wp-error-summary { margin:8px 0 0 18px; }
        .wp-error-summary a { color:#b32d2e; text-decoration:underline; }
        .wp-field-error { border-color:#d63638 !important; box-shadow:0 0 0 1px #d63638 !important; }
        .wp-error-text { color:#d63638; font-size:12px; margin-top:5px; }
        .wp-grid { display:grid; grid-template-columns:minmax(0, 1fr) 280px; gap:18px; align-items:start; }
        .wp-title-input { width:100%; height:38px; border:1px solid #8c8f94; padding:4px 8px; font-size:1.7em; line-height:1.1; background:#fff; }
        .wp-permalink { margin:8px 0 16px; color:#50575e; font-size:13px; }
        .wp-permalink input { width:220px; border:1px solid #c3c4c7; padding:4px 6px; }
        .wp-builder-row { margin: 22px 0; }
        .wp-blue-btn { background:#3858e9; border:1px solid #3858e9; color:#fff; padding:11px 28px; font-weight:600; cursor:pointer; }
        .wp-toolbar-row { display:flex; justify-content:space-between; align-items:end; margin-top:10px; }
        .wp-media-actions { display:flex; gap:6px; }
        .wp-secondary, .wp-tab, .wp-quicktag { background:#f6f7f7; border:1px solid #2271b1; color:#2271b1; cursor:pointer; padding:7px 10px; text-decoration:none; }
        .wp-tab { border-color:#c3c4c7; color:#50575e; border-bottom:0; background:#f6f7f7; }
        .wp-tab.active { background:#fff; color:#1d2327; }
        .wp-editor-wrap { border:1px solid #c3c4c7; background:#fff; }
        .wp-quicktags { border-bottom:1px solid #dcdcde; padding:6px; background:#f6f7f7; display:flex; flex-wrap:wrap; gap:4px; }
        .wp-quicktag { padding:4px 8px; border-color:#8c8f94; color:#1d2327; font-size:12px; }
        #post-content { min-height:360px; }
        #html-editor { display:none; width:100%; min-height:430px; border:0; padding:12px; font-family:Consolas, monospace; font-size:13px; resize:vertical; }
        .wp-word-count { border-top:1px solid #dcdcde; color:#50575e; padding:6px 8px; font-size:12px; }
        .wp-box { background:#fff; border:1px solid #c3c4c7; margin-bottom:14px; }
        .wp-box-title { margin:0; padding:9px 12px; border-bottom:1px solid #dcdcde; font-size:14px; font-weight:600; display:flex; justify-content:space-between; align-items:center; cursor:pointer; }
        .wp-box-body { padding:12px; }
        .wp-box.is-collapsed .wp-box-body { display:none; }
        .wp-input, .wp-select, .wp-textarea { width:100%; border:1px solid #8c8f94; background:#fff; padding:6px 8px; min-height:32px; }
        .wp-textarea { resize:vertical; min-height:64px; }
        .wp-help { color:#646970; font-size:12px; line-height:1.5; margin-top:6px; }
        .wp-publish-actions { display:flex; justify-content:space-between; gap:8px; margin-bottom:12px; }
        .wp-status-line { margin:10px 0; font-size:13px; }
        .wp-side-submit { background:#f6f7f7; border-top:1px solid #dcdcde; margin:12px -12px -12px; padding:10px 12px; text-align:right; }
        .wp-primary { background:#2271b1; border:1px solid #2271b1; color:#fff; cursor:pointer; padding:7px 14px; font-weight:600; }
        .wp-danger { background:#fff; border:1px solid #d63638; color:#d63638; cursor:pointer; padding:6px 10px; }
        .wp-seo-mini { background:#fce8e8; color:#d63638; margin:10px -12px; padding:10px 12px; font-weight:600; }
        .wp-cat-list { max-height:170px; overflow:auto; border:1px solid #dcdcde; padding:8px; }
        .wp-cat-list label { display:block; margin:4px 0; font-size:13px; }
        .wp-inline-status { min-height:17px; margin-top:6px; color:#646970; font-size:11px; }
        .wp-inline-status.is-error { color:#b91c1c; }
        .wp-tag-picker { position:relative; display:flex; flex-wrap:wrap; align-items:center; gap:6px; min-height:40px; border:1px solid #8c8f94; background:#fff; padding:5px 7px; cursor:text; }
        .wp-tag-picker:focus-within { border-color:#2271b1; box-shadow:0 0 0 1px #2271b1; }
        .wp-tag-chip { display:inline-flex; align-items:center; gap:5px; max-width:100%; border-radius:999px; background:#8b0000; padding:4px 8px 4px 10px; color:#fff; font-size:12px; font-weight:700; }
        .wp-tag-chip span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .wp-tag-chip button { border:0; background:transparent; color:#fff; padding:0; font-size:15px; line-height:1; cursor:pointer; }
        .wp-tag-entry { flex:1 1 110px; min-width:90px; border:0 !important; outline:0 !important; box-shadow:none !important; padding:4px 2px; }
        .wp-tag-suggestions { position:absolute; z-index:30; top:calc(100% + 5px); left:0; right:0; display:none; max-height:180px; overflow:auto; border:1px solid #dcdcde; background:#fff; box-shadow:0 10px 25px rgba(15,23,42,.14); }
        .wp-tag-suggestions.is-open { display:block; }
        .wp-tag-suggestion { display:flex; justify-content:space-between; width:100%; border:0; border-bottom:1px solid #f0f0f1; background:#fff; padding:8px 10px; text-align:left; cursor:pointer; }
        .wp-tag-suggestion:hover,.wp-tag-suggestion:focus { background:#fff1f2; color:#8b0000; }
        .seo-preview { padding:14px 12px; border-bottom:1px solid #f0f0f1; }
        .seo-preview-title { color:#1a0dab; font-size:16px; margin-bottom:4px; }
        .seo-preview-url { color:#006621; font-size:12px; word-break:break-all; }
        .seo-preview-description { color:#4d5156; font-size:13px; margin-top:4px; }
        .seo-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .rank-score-row { display:grid; grid-template-columns:1fr 80px; gap:0; align-items:center; }
        .rank-score-cell { background:#fce8e8; color:#d63638; border:1px solid #f3c5c5; border-left:0; padding:7px; text-align:center; font-weight:600; }
        .rank-section { border-top:1px solid #dcdcde; padding:12px; }
        .rank-pill { background:#f4a6a6; color:#fff; border-radius:12px; font-size:11px; padding:2px 8px; margin-left:6px; }
        .rank-check { display:flex; align-items:center; gap:8px; margin:8px 0; color:#50575e; font-size:13px; }
        .rank-check .mark { width:14px; height:14px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:10px; background:#d63638; }
        .rank-check[data-state="good"] .mark { background:#00a32a; }
        .layout-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
        .layout-option { border:2px solid transparent; cursor:pointer; display:block; padding:6px; }
        .layout-option:has(input:checked) { border-color:#2271b1; }
        .layout-option input { margin-bottom:5px; }
        .layout-thumb { height:95px; border:1px solid #ccd0d4; background:#fff; padding:8px; }
        .layout-thumb .line { height:6px; background:#dcdcde; margin-bottom:6px; }
        .layout-thumb .hero { height:35px; background:#a7c7e7; margin-bottom:6px; }
        .media-modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,.58); z-index:1000; }
        .media-modal.is-open { display:flex; }
        .media-panel { width:min(980px, 92vw); max-height:88vh; background:#fff; border:1px solid #1d2327; display:grid; grid-template-rows:auto auto 1fr auto; }
        .media-head, .media-foot { padding:12px 16px; border-bottom:1px solid #dcdcde; display:flex; justify-content:space-between; align-items:center; }
        .media-foot { border-top:1px solid #dcdcde; border-bottom:0; }
        .media-tabs { display:flex; gap:0; border-bottom:1px solid #dcdcde; padding:0 16px; background:#f6f7f7; }
        .media-tabs button { border:0; border-right:1px solid #dcdcde; background:transparent; cursor:pointer; padding:10px 14px; }
        .media-tabs button.is-active { background:#fff; font-weight:600; }
        .media-body { min-height:440px; overflow:auto; }
        .media-panel-view { padding:18px; }
        .media-upload-drop { border:2px dashed #c3c4c7; min-height:245px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; text-align:center; background:#fbfbfc; }
        .media-upload-status { color:#50575e; min-height:20px; }
        .media-library { overflow:auto; display:grid; grid-template-columns:repeat(auto-fill, minmax(115px, 1fr)); gap:10px; align-content:start; }
        .media-tile { border:2px solid transparent; background:#f6f7f7; min-height:100px; cursor:pointer; padding:5px; text-align:center; overflow:hidden; }
        .media-tile.is-selected { border-color:#2271b1; }
        .media-tile img { width:100%; height:86px; object-fit:cover; display:block; margin-bottom:4px; }
        .media-selected-preview { display:flex; align-items:center; gap:10px; color:#50575e; font-size:13px; }
        .media-selected-preview img { width:48px; height:48px; object-fit:cover; border:1px solid #dcdcde; }
        .revisions-list { max-height:210px; overflow:auto; }
        .revision-row { border-top:1px solid #dcdcde; padding:8px 0; display:flex; justify-content:space-between; gap:8px; align-items:center; }
        @media (max-width: 980px) { .wp-grid { grid-template-columns:1fr; } .wp-side-column { order:-1; } .layout-grid { grid-template-columns:1fr 1fr; } }
    </style>
    <style>
        body{background:#fffaf9}
        .wp-post-screen{width:100%;max-width:none;margin:0;background:#fffaf9;padding:26px 30px 32px;color:#1f2937;font-family:Inter,Arial,sans-serif}
        .wp-post-screen>form,.wp-heading,.wp-notice{width:100%}
        .wp-heading{margin:0 0 13px;color:#111827;font-size:25px;font-weight:800}
        .wp-grid{grid-template-columns:minmax(0,1fr) 286px;gap:22px;align-items:start}
        .wp-grid>main{min-width:0}
        .wp-title-input{height:48px;border:1px solid #d8caca;border-radius:6px;padding:8px 13px;font-size:20px;box-shadow:0 1px 2px rgba(127,29,29,.03)}
        .wp-title-input::placeholder{color:#9ca3af}
        .wp-permalink{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:10px 0 16px;color:#4b5563;font-size:12px}
        .wp-permalink input{width:220px;min-height:34px;border-color:#decaca;border-radius:6px;background:#fff}
        .wp-secondary,.wp-tab,.wp-quicktag{border-color:#ee857c;border-radius:5px;background:#fff;color:#c3261b}
        .wp-secondary:hover,.wp-tab.active{border-color:#d92d20;background:#fff5f4;color:#a90000}
        .wp-toolbar-row{margin-top:0}
        .wp-media-actions{padding-bottom:7px}
        .wp-editor-wrap{overflow:hidden;border-color:#dfcece;border-radius:8px;background:#fff;box-shadow:0 2px 10px rgba(127,29,29,.035)}
        .wp-quicktags{padding:6px 8px;border-color:#eadada;background:#fff}
        .wp-quicktag{min-width:30px;border-color:#efd2cf;background:#fff;color:#374151;font-weight:700}
        .wp-editor-wrap .tox-tinymce{min-height:410px!important;border:0!important;border-radius:0!important}
        #post-content{min-height:410px}
        .wp-word-count{border-color:#eadada;padding:7px 10px;background:#fff;color:#64748b}
        .wp-box{overflow:hidden;margin:12px 0;border:1px solid #ead7d7;border-radius:8px;background:#fff;box-shadow:0 2px 10px rgba(127,29,29,.03)}
        .wp-box-title{min-height:42px;padding:10px 13px;border-color:#efdfdf;background:#fff;font-size:13px;font-weight:800}
        .wp-box-body{padding:13px}
        .wp-input,.wp-select,.wp-textarea{min-height:36px;border-color:#decaca;border-radius:5px;padding:7px 9px}
        .wp-textarea{min-height:76px}
        .wp-side-column{position:sticky;top:14px}
        .wp-side-column .wp-box{margin-top:0;margin-bottom:12px;box-shadow:0 4px 14px rgba(30,41,59,.07)}
        .wp-publish-actions{gap:10px;margin-bottom:17px}
        .wp-publish-actions>*{flex:1}
        .wp-status-line{display:grid;grid-template-columns:1fr;gap:5px;margin:12px 0;font-size:11px;font-weight:700}
        .wp-primary{min-height:36px;border:1px solid #d92d20;border-radius:5px;background:#d92d20;padding:7px 15px;color:#fff}
        .wp-primary:hover{background:#b91c1c}
        .wp-danger{min-height:35px;border-color:#e36b62;border-radius:5px;color:#c3261b}
        .wp-side-submit{display:flex;gap:7px;margin:13px -13px -13px;padding:11px 13px;border-color:#efdfdf;background:#fff}
        .wp-side-submit>*{flex:1;text-align:center}
        .wp-seo-mini{margin:10px -13px;padding:9px 13px;background:#fff1ef;color:#b91c1c}
        .wp-cat-list{max-height:190px;border-color:#ead6d6;border-radius:5px;background:#fff}
        .wp-tag-picker{border-color:#decaca;border-radius:5px}
        .seo-settings-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(260px,.95fr);gap:24px;align-items:start}
        .seo-fields{display:grid;gap:11px}
        .seo-fields label{display:grid;gap:5px;font-size:11px}
        .seo-preview{padding:0;border:0}
        .seo-preview>strong{display:block;margin-bottom:7px;font-size:11px}
        .seo-preview-card{min-height:145px;border:1px solid #e3d6d6;border-radius:7px;background:#fff;padding:14px;box-shadow:0 2px 7px rgba(15,23,42,.04)}
        .seo-preview-card small{color:#374151;font-weight:700}
        .seo-preview-title{margin:7px 0 4px;font-size:17px}
        .seo-preview-description{line-height:1.45}
        .rank-score-row{grid-template-columns:1fr 72px}
        .rank-score-cell{border-color:#f0c7c3;border-radius:0 5px 5px 0;background:#fff0ee;color:#c3261b}
        .seo-advanced,.seo-analysis{display:inline-block;width:calc(50% - 2px);vertical-align:top;border-top:1px solid #efdfdf;background:#fff}
        .seo-advanced[open],.seo-analysis[open]{display:block;width:100%}
        .wp-box.is-collapsed .seo-advanced,.wp-box.is-collapsed .seo-analysis{display:none}
        .seo-advanced>summary,.seo-analysis>summary{display:flex;align-items:center;justify-content:space-between;padding:10px 13px;color:#4b5563;font-size:11px;font-weight:800;cursor:pointer;list-style:none}
        .seo-advanced>summary::-webkit-details-marker,.seo-analysis>summary::-webkit-details-marker{display:none}
        .seo-advanced-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 13px 13px}
        .seo-advanced-grid>div{grid-column:1/-1;display:flex;gap:16px}
        .rank-section{display:grid;grid-template-columns:1fr 1fr;gap:2px 16px;padding:2px 13px 12px;border:0}
        .rank-check{margin:4px 0;font-size:11px}
        .layout-grid{gap:10px}
        .layout-option{border:1px solid #ead6d6;border-radius:6px;padding:7px}
        .layout-option:has(input:checked){border-color:#e34237;background:#fff7f6}
        .layout-thumb{height:82px;border:0;background:#fff5f4}
        .revision-row{padding:7px 0;border-color:#efdfdf}
        .wp-bottom-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:14px;border:1px solid #f0dada;border-radius:8px;background:#fff1ef;padding:10px 12px}
        .wp-bottom-actions>div{display:flex;gap:8px}
        .wp-hidden-delete-form{display:none!important}
        .media-panel{overflow:hidden;border-color:#d8caca;border-radius:9px}
        .media-head,.media-foot,.media-tabs{border-color:#eadada}
        .media-upload-drop{border-color:#dfb8b8;border-radius:8px;background:#fffafa}
        @media(max-width:1100px){.wp-grid{grid-template-columns:minmax(0,1fr) 270px}.seo-settings-grid{grid-template-columns:1fr}.rank-section{grid-template-columns:1fr}}
        @media(max-width:900px){.wp-grid{grid-template-columns:1fr}.wp-side-column{position:static}.layout-grid{grid-template-columns:1fr 1fr}}
        @media(max-width:640px){.wp-post-screen{padding:16px 10px 24px}.wp-heading{font-size:21px}.wp-title-input{height:45px;font-size:18px}.wp-permalink input{width:100%}.seo-advanced,.seo-analysis{display:block;width:100%}.seo-advanced-grid,.layout-grid{grid-template-columns:1fr}.seo-advanced-grid>div{grid-column:auto;flex-direction:column}.wp-bottom-actions{align-items:stretch;flex-direction:column}.wp-bottom-actions>div>*{flex:1}}
    </style>

    <div class="wp-post-screen">
        <h1 class="wp-heading">{{ $isEdit ? 'Edit Post' : 'Add Post' }}</h1>

        @if (session('status'))
            <div class="wp-notice">{{ session('status') }}</div>
        @endif

        @if (isset($errors) && $errors->any())
            <div class="wp-notice error">
                <strong>Post was not saved. Please fix the highlighted fields below.</strong>
                <ul class="wp-error-summary">
                    @foreach ($errors->messages() as $field => $messages)
                        <li><a href="#field-{{ str_replace(['.', '_'], '-', $field) }}" data-error-link="{{ $field }}">{{ $errorLabels[$field] ?? ucfirst(str_replace('_', ' ', $field)) }}: {{ $messages[0] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="post-form" method="POST" action="{{ $action }}">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <input type="hidden" name="featured_image_id" id="featured-image-id" value="{{ $selectedFeaturedId ?: '' }}">
            <input type="hidden" name="featured_image" id="featured-image-url" value="{{ $featuredUrl }}">

            <div class="wp-grid">
                <main>
                    <input id="post-title" class="wp-title-input{{ $errorClass('title') }}" name="title" value="{{ old('title', $post->title) }}" placeholder="Add title" required aria-describedby="{{ $hasError('title') ? $errorId('title') : '' }}">
                    @error('title')
                        <div class="wp-error-text" id="{{ $errorId('title') }}">{{ $message }}</div>
                    @enderror
                    <div class="wp-permalink">
                        Permalink:
                        <span>/blog/</span>
                        <input id="post-slug" class="{{ trim($errorClass('slug')) }}" name="slug" value="{{ old('slug', $post->slug) }}" placeholder="auto-generated" aria-describedby="{{ $hasError('slug') ? $errorId('slug') : '' }}">
                        <button class="wp-secondary" type="button" id="generate-slug">Generate Slug</button>
                        @if ($isPubliclyViewable)
                            <a class="wp-secondary" href="{{ $publicPostPath }}" target="_blank" rel="noopener">View Public Post</a>
                        @endif
                        @error('slug')
                            <div class="wp-error-text" id="{{ $errorId('slug') }}">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="wp-builder-row" id="builder-row" style="display:none;">
                        <button class="wp-blue-btn" type="button">Edit with Builder</button>
                    </div>

                    <div class="wp-toolbar-row">
                        <div class="wp-media-actions">
                            <button type="button" class="wp-secondary" data-media-open="insert">Add Media</button>
                        </div>
                        <div>
                            <button type="button" class="wp-tab active" data-editor-tab="visual">Visual</button>
                            <button type="button" class="wp-tab" data-editor-tab="code">Code</button>
                        </div>
                    </div>

                    <div id="field-content" class="wp-editor-wrap{{ $errorClass('content') }}">
                        <div class="wp-quicktags">
                            @foreach (['b','i','link','blockquote','del','ins','img','ul','ol','li','code'] as $tag)
                                <button type="button" class="wp-quicktag" data-quicktag="{{ $tag }}">{{ $tag }}</button>
                            @endforeach
                        </div>
                        <textarea id="post-content" name="content">{{ old('content', $post->content) }}</textarea>
                        <textarea id="html-editor" aria-label="HTML source editor"></textarea>
                        <div class="wp-word-count">Word count: <span id="word-count">0</span></div>
                    </div>
                    @error('content')
                        <div class="wp-error-text" id="{{ $errorId('content') }}">{{ $message }}</div>
                    @enderror

                    <section class="wp-box">
                        <h2 class="wp-box-title">Excerpt <span>^</span></h2>
                        <div class="wp-box-body">
                            <textarea id="field-excerpt" class="wp-textarea{{ $errorClass('excerpt') }}" name="excerpt">{{ old('excerpt', $post->excerpt) }}</textarea>
                            @error('excerpt')
                                <div class="wp-error-text" id="{{ $errorId('excerpt') }}">{{ $message }}</div>
                            @enderror
                            <div class="wp-help">Excerpts are optional hand-crafted summaries used by themes and SEO previews.</div>
                        </div>
                    </section>

                    <section class="wp-box seo-settings-box">
                        <h2 class="wp-box-title">SEO Settings <span>^</span></h2>
                        <div class="wp-box-body seo-settings-grid">
                            <div class="seo-fields">
                                <label><strong>SEO Title</strong><input id="seo-title" class="wp-input{{ $errorClass('seo_title') }}" name="seo_title" value="{{ old('seo_title', $post->seo_title) }}" placeholder="Enter SEO title...">@error('seo_title')<div class="wp-error-text" id="{{ $errorId('seo_title') }}">{{ $message }}</div>@enderror</label>
                                <label><strong>Meta Description</strong><textarea id="seo-description" class="wp-textarea{{ $errorClass('seo_description') }}" name="seo_description" placeholder="Enter meta description...">{{ old('seo_description', $post->seo_description) }}</textarea>@error('seo_description')<div class="wp-error-text" id="{{ $errorId('seo_description') }}">{{ $message }}</div>@enderror</label>
                                <label><strong>Focus Keyword</strong></label>
                                <div class="rank-score-row"><input id="focus-keyword" class="wp-input{{ $errorClass('focus_keyword') }}" name="focus_keyword" value="{{ old('focus_keyword', $post->focus_keyword) }}" placeholder="Enter focus keyword..."><div class="rank-score-cell"><span id="seo-score-inline">{{ (int) old('seo_score', $post->seo_score ?? 0) }}</span> / 100</div></div>
                                @error('focus_keyword')<div class="wp-error-text" id="{{ $errorId('focus_keyword') }}">{{ $message }}</div>@enderror
                                <div class="wp-help">Choose the main keyword you want to rank for.</div>
                            </div>
                            <div class="seo-preview"><strong>Preview (Google)</strong><div class="seo-preview-card"><small>Art Z</small><div id="seo-preview-url" class="seo-preview-url">/blog/{{ old('slug', $post->slug ?: 'post-slug') }}</div><div id="seo-preview-title" class="seo-preview-title">{{ old('seo_title', $post->seo_title) ?: old('title', $post->title ?: 'News ART') }}</div><div id="seo-preview-description" class="seo-preview-description">{{ old('seo_description', $post->seo_description ?: $post->excerpt) }}</div></div></div>
                        </div>
                        <details class="seo-advanced"><summary>Advanced SEO</summary><div class="seo-advanced-grid"><label><strong>Canonical URL</strong><input id="canonical-url" class="wp-input{{ $errorClass('canonical_url') }}" name="canonical_url" value="{{ old('canonical_url', $post->canonical_url) }}">@error('canonical_url')<div class="wp-error-text" id="{{ $errorId('canonical_url') }}">{{ $message }}</div>@enderror</label><label><strong>Schema</strong><select id="schema-type" class="wp-select{{ $errorClass('schema_type') }}" name="schema_type">@foreach ($schemaTypes as $value => $label)<option value="{{ $value }}" @selected(old('schema_type', $post->schema_type ?: 'BlogPosting') === $value)>{{ $label }}</option>@endforeach</select>@error('schema_type')<div class="wp-error-text" id="{{ $errorId('schema_type') }}">{{ $message }}</div>@enderror</label><div><label><input type="checkbox" name="robots_index" value="1" @checked(old('robots_index', $post->robots_index ?? true))> Index this post</label> <label><input type="checkbox" name="robots_follow" value="1" @checked(old('robots_follow', $post->robots_follow ?? true))> Follow links</label></div></div></details>
                        <details class="seo-analysis"><summary><strong>SEO Analysis</strong><span id="seo-error-pill" class="rank-pill">0 Errors</span></summary><div class="rank-section">
                            @foreach ([
                                'keyword-title' => 'Add Focus Keyword to the SEO title.',
                                'keyword-description' => 'Add Focus Keyword to your SEO Meta Description.',
                                'keyword-url' => 'Use Focus Keyword in the URL.',
                                'keyword-content' => 'Use Focus Keyword in the content.',
                                'content-length' => 'Content should be 600 words or longer.',
                                'description-length' => 'Meta description should be 120-160 characters.',
                                'title-length' => 'SEO title should be 35-65 characters.',
                                'featured-image' => 'Add a featured image.',
                                'category' => 'Assign the post to a category.',
                                'tags' => 'Add at least one relevant tag.',
                            ] as $key => $label)
                                <div class="rank-check" data-check="{{ $key }}" data-state="bad"><span class="mark">x</span><span>{{ $label }}</span></div>
                            @endforeach
                        </div></details>
                    </section>

                    <section class="wp-box">
                        <h2 class="wp-box-title">Post Layout Options <span>^</span></h2>
                        <div class="wp-box-body">
                            <div class="layout-grid">
                                @foreach ($templates as $value => $label)
                                    <label class="layout-option">
                                        <input type="radio" name="template" value="{{ $value }}" @checked($selectedTemplate === $value)>
                                        <div class="layout-thumb">
                                            <div class="line"></div><div class="line" style="width:75%"></div><div class="hero"></div><div class="line"></div>
                                        </div>
                                        <div class="wp-help">{{ $label }}</div>
                                    </label>
                                @endforeach
                            </div>
                            @error('template')<div class="wp-error-text" id="{{ $errorId('template') }}">{{ $message }}</div>@enderror
                            <input type="hidden" name="layout" id="post-layout" value="{{ old('layout', $post->layout ?: $selectedTemplate) }}">
                            @error('layout')<div class="wp-error-text" id="{{ $errorId('layout') }}">{{ $message }}</div>@enderror
                        </div>
                    </section>

                    @if ($isEdit && $post->revisions->isNotEmpty())
                        <section class="wp-box">
                            <h2 class="wp-box-title">Revisions <span>^</span></h2>
                            <div class="wp-box-body revisions-list">
                                @foreach ($post->revisions->take(4) as $revision)
                                    <div class="revision-row">
                                        <span>{{ $revision->revision_type }} - {{ $revision->created_at?->format('Y-m-d H:i') }} - {{ $revision->user?->name }}</span>
                                        <div style="display:flex;gap:6px;">
                                            <form method="POST" action="{{ route('admin.plugins.blog.posts.revisions.restore', [$post, $revision], false) }}">
                                                @csrf
                                                <button class="wp-secondary" type="submit">Restore</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.plugins.blog.posts.revisions.destroy', [$post, $revision], false) }}" onsubmit="return confirm('Delete this revision?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="wp-danger" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                                <div class="wp-help">Only the latest 4 revisions are kept.</div>
                            </div>
                        </section>
                    @endif
                </main>

                <aside class="wp-side-column">
                    <section class="wp-box">
                        <h2 class="wp-box-title">Publish <span>^</span></h2>
                        <div class="wp-box-body">
                            <div class="wp-publish-actions">
                                <button class="wp-secondary" name="intent" value="draft" type="submit">Save Draft</button>
                                <button class="wp-primary" name="intent" value="publish" type="submit">{{ $isEdit ? 'Update' : 'Publish' }}</button>
                            </div>
                            <div class="wp-status-line">Status:
                                <select id="field-status" class="wp-select{{ $errorClass('status') }}" name="status">
                                    @foreach (['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled', 'private' => 'Private'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', $post->status ?: 'draft') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')<div class="wp-error-text" id="{{ $errorId('status') }}">{{ $message }}</div>@enderror
                            </div>
                            <div class="wp-status-line">Visibility:
                                <select class="wp-select{{ $errorClass('visibility') }}" name="visibility" id="post-visibility">
                                    @foreach (['public' => 'Public', 'private' => 'Private', 'password' => 'Password protected'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('visibility', $post->visibility ?: 'public') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('visibility')<div class="wp-error-text" id="{{ $errorId('visibility') }}">{{ $message }}</div>@enderror
                            </div>
                            <div class="wp-status-line" id="password-row">Password:
                                <input id="field-password" class="wp-input{{ $errorClass('password') }}" name="password" value="{{ old('password', $post->password) }}">
                                @error('password')<div class="wp-error-text" id="{{ $errorId('password') }}">{{ $message }}</div>@enderror
                            </div>
                            <div class="wp-status-line">Publish:
                                <input id="field-published-at" class="wp-input{{ $errorClass('published_at') }}" type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}">
                                @error('published_at')<div class="wp-error-text" id="{{ $errorId('published_at') }}">{{ $message }}</div>@enderror
                            </div>
                            <div class="wp-status-line">Schedule:
                                <input id="field-scheduled-at" class="wp-input{{ $errorClass('scheduled_at') }}" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $post->scheduled_at?->format('Y-m-d\TH:i')) }}">
                                @error('scheduled_at')<div class="wp-error-text" id="{{ $errorId('scheduled_at') }}">{{ $message }}</div>@enderror
                            </div>
                            <div class="wp-seo-mini">SEO: <span id="seo-sidebar-score">{{ (int) old('seo_score', $post->seo_score ?? 0) }}</span> / 100</div>
                            <div class="wp-side-submit">
                                <button class="wp-secondary" name="intent" value="schedule" type="submit">Schedule</button>
                                @if ($isPubliclyViewable)<a class="wp-secondary" href="{{ $publicPostPath }}" target="_blank" rel="noopener">Preview Changes</a>@else<button class="wp-secondary" name="intent" value="preview" type="submit">Preview Draft</button>@endif
                            </div>
                        </div>
                    </section>

                    <section class="wp-box">
                        <h2 class="wp-box-title">Categories <span>^</span></h2>
                        <div class="wp-box-body">
                            <div class="wp-cat-list" id="category-list">
                                <label><input type="radio" name="category_id" value="" @checked(! old('category_id', $post->category_id))> Uncategorized</label>
                                @foreach ($categories as $category)
                                    <label><input type="radio" name="category_id" value="{{ $category->id }}" @checked((int) old('category_id', $post->category_id) === $category->id)> {{ $category->name }}</label>
                                @endforeach
                            </div>
                            @error('category_id')<div class="wp-error-text" id="{{ $errorId('category_id') }}">{{ $message }}</div>@enderror
                            <div style="display:flex; gap:6px; margin-top:8px;">
                                <input id="new-category-name" class="wp-input" placeholder="New category name">
                                <button type="button" class="wp-secondary" id="add-category">Add</button>
                            </div>
                            <div class="wp-inline-status" id="category-quick-status" role="status"></div>
                        </div>
                    </section>

                    <section class="wp-box">
                        <h2 class="wp-box-title">Tags <span>^</span></h2>
                        <div class="wp-box-body">
                            <input id="post-tags" type="hidden" name="tags" value="{{ $selectedTags->implode(',') }}">
                            <div class="wp-tag-picker" id="tag-picker">
                                <div id="tag-chips" style="display:contents"></div>
                                <input id="post-tag-input" class="wp-tag-entry" autocomplete="off" placeholder="Type or select a tag">
                                <div class="wp-tag-suggestions" id="tag-suggestions" role="listbox"></div>
                            </div>
                            <div class="wp-help">Choose an existing tag or press Enter/comma to add a new one.</div>
                        </div>
                    </section>

                    <section class="wp-box">
                        <h2 class="wp-box-title">Featured image <span>^</span></h2>
                        <div class="wp-box-body">
                            <button type="button" class="wp-secondary" data-media-open="featured">Set featured image</button>
                            <input class="wp-input{{ $errorClass('featured_image_alt') }}" style="margin-top:8px;" name="featured_image_alt" id="featured-image-alt" value="{{ $featuredAlt }}" placeholder="Image alt text">
                            @error('featured_image_id')<div class="wp-error-text" id="{{ $errorId('featured_image_id') }}">{{ $message }}</div>@enderror
                            @error('featured_image_alt')<div class="wp-error-text" id="{{ $errorId('featured_image_alt') }}">{{ $message }}</div>@enderror
                            <div id="featured-preview" style="margin-top:10px;">
                                @if ($featuredUrl)
                                    <img src="{{ $featuredUrl }}" alt="" style="max-width:100%; display:block;">
                                @endif
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
            <div class="wp-bottom-actions">@if($isEdit)<button class="wp-danger" type="submit" form="delete-current-post">Move to Trash</button>@else<span></span>@endif<div><button class="wp-secondary" name="intent" value="draft" type="submit">Save Draft</button><button class="wp-primary" name="intent" value="publish" type="submit">{{ $isEdit ? 'Update' : 'Publish' }}</button></div></div>
        </form>

        @if ($isEdit)
            <form id="delete-current-post" class="wp-hidden-delete-form" method="POST" action="{{ route('admin.plugins.blog.posts.destroy', $post, false) }}">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>

    <div class="media-modal" id="media-modal" aria-hidden="true">
        <div class="media-panel">
            <div class="media-head"><strong>Media Library</strong><button class="wp-secondary" type="button" id="media-close">Close</button></div>
            <div class="media-tabs">
                <button type="button" class="is-active" data-media-tab="upload">Upload files</button>
                <button type="button" data-media-tab="library">Media Library</button>
            </div>
            <div class="media-body">
                <div class="media-panel-view" data-media-panel="upload">
                    <div class="media-upload-drop">
                        <strong>Drop files to upload</strong>
                        <span>or</span>
                        <button type="button" class="wp-secondary" id="media-select-file">Select image</button>
                        <input type="file" id="media-file" accept=".png,.jpg,.jpeg,.webp,.gif,.ico" hidden>
                        <div class="media-upload-status" id="media-upload-status">Selecting an image uploads it immediately.</div>
                    </div>
                </div>
                <div class="media-panel-view" data-media-panel="library" hidden>
                    <div class="media-library" id="media-library">
                        <p class="wp-help">Open the library to load existing media.</p>
                    </div>
                </div>
            </div>
            <div class="media-foot">
                <div class="media-selected-preview" id="selected-media-details">Select or upload an image.</div>
                <div>
                    <button class="wp-primary" type="button" id="media-use" disabled>Use image</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        (() => {
            const csrf = @json(csrf_token());
            const routes = {
                autosave: @json(route('admin.plugins.blog.posts.autosave', [], false)),
                slug: @json(route('admin.plugins.blog.posts.slug', [], false)),
                mediaIndex: @json(route('admin.media.index', [], false)),
                mediaStore: @json(route('admin.media.store', [], false)),
                categoryQuick: @json(route('admin.plugins.blog.categories.quick-store', [], false)),
            };
            let postId = @json($post->id);
            let mediaMode = 'insert';
            let selectedMedia = null;
            let currentTab = 'visual';
            let mediaLibraryLoaded = false;
            let mediaLibraryLoading = false;

            const title = document.getElementById('post-title');
            const slug = document.getElementById('post-slug');
            const content = document.getElementById('post-content');
            const htmlEditor = document.getElementById('html-editor');
            const focus = document.getElementById('focus-keyword');
            const seoTitle = document.getElementById('seo-title');
            const seoDescription = document.getElementById('seo-description');
            const tags = document.getElementById('post-tags');
            const tagInput = document.getElementById('post-tag-input');
            const tagChips = document.getElementById('tag-chips');
            const tagSuggestions = document.getElementById('tag-suggestions');
            const availableTags = @json($availableTags->map(fn ($tag) => ['name' => $tag->name, 'slug' => $tag->slug])->values());
            let selectedTags = @json($selectedTags);
            const wordCount = document.getElementById('word-count');
            const scoreInline = document.getElementById('seo-score-inline');
            const scoreSidebar = document.getElementById('seo-sidebar-score');
            const errorPill = document.getElementById('seo-error-pill');
            const previewTitle = document.getElementById('seo-preview-title');
            const previewUrl = document.getElementById('seo-preview-url');
            const previewDescription = document.getElementById('seo-preview-description');
            const visibility = document.getElementById('post-visibility');
            const passwordRow = document.getElementById('password-row');

            function normalizedTag(value) {
                return String(value || '').trim().replace(/\s+/g, ' ');
            }

            function syncTagValue() {
                tags.value = selectedTags.join(',');
                tags.dispatchEvent(new Event('input', {bubbles:true}));
            }

            function renderTagChips() {
                tagChips.innerHTML = '';
                selectedTags.forEach(name => {
                    const chip = document.createElement('span');
                    chip.className = 'wp-tag-chip';
                    const label = document.createElement('span');
                    label.textContent = name;
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.setAttribute('aria-label', `Remove ${name}`);
                    remove.textContent = '×';
                    remove.addEventListener('click', () => {
                        selectedTags = selectedTags.filter(item => item.toLocaleLowerCase() !== name.toLocaleLowerCase());
                        renderTagChips();
                        syncTagValue();
                        tagInput.focus();
                    });
                    chip.append(label, remove);
                    tagChips.appendChild(chip);
                });
            }

            function addTag(value) {
                const name = normalizedTag(value);
                if (!name) return;
                if (!selectedTags.some(item => item.toLocaleLowerCase() === name.toLocaleLowerCase())) selectedTags.push(name);
                tagInput.value = '';
                tagSuggestions.classList.remove('is-open');
                renderTagChips();
                syncTagValue();
            }

            function showTagSuggestions() {
                const query = normalizedTag(tagInput.value).toLocaleLowerCase();
                const matches = availableTags.filter(tag => !selectedTags.some(item => item.toLocaleLowerCase() === tag.name.toLocaleLowerCase()) && (!query || tag.name.toLocaleLowerCase().includes(query))).slice(0, 8);
                tagSuggestions.innerHTML = '';
                matches.forEach(tag => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'wp-tag-suggestion';
                    const name = document.createElement('span');
                    name.textContent = tag.name;
                    const slug = document.createElement('small');
                    slug.textContent = tag.slug;
                    button.append(name, slug);
                    button.addEventListener('mousedown', event => { event.preventDefault(); addTag(tag.name); });
                    tagSuggestions.appendChild(button);
                });
                tagSuggestions.classList.toggle('is-open', matches.length > 0);
            }

            function getEditorContent() {
                if (window.tinymce && tinymce.get('post-content')) {
                    return tinymce.get('post-content').getContent();
                }
                return content.value;
            }

            function setEditorContent(value) {
                if (window.tinymce && tinymce.get('post-content')) {
                    tinymce.get('post-content').setContent(value || '');
                }
                content.value = value || '';
                htmlEditor.value = value || '';
                refreshSeo();
            }

            function syncFromVisual() {
                const html = getEditorContent();
                content.value = html;
                htmlEditor.value = html;
            }

            function syncFromCode() {
                setEditorContent(htmlEditor.value);
            }

            function plainText(html) {
                const div = document.createElement('div');
                div.innerHTML = html || '';
                return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
            }

            function slugify(value) {
                const arabic = {'ا':'a','أ':'a','إ':'i','آ':'a','ب':'b','ت':'t','ث':'th','ج':'j','ح':'h','خ':'kh','د':'d','ذ':'dh','ر':'r','ز':'z','س':'s','ش':'sh','ص':'s','ض':'d','ط':'t','ظ':'z','ع':'a','غ':'gh','ف':'f','ق':'q','ك':'k','ل':'l','م':'m','ن':'n','ه':'h','ة':'a','و':'w','ؤ':'w','ي':'y','ى':'a','ئ':'y'};
                return Array.from((value || '').toString()).map(char => arabic[char] ?? char).join('').trim().toLowerCase()
                    .replace(/[\s_]+/g, '-')
                    .normalize('NFKD').replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9-]/g, '')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
            }

            async function generateSlug() {
                if (!title.value.trim()) return;
                const response = await fetch(routes.slug, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
                    body: JSON.stringify({title: title.value, post_id: postId})
                });
                if (response.ok) {
                    const data = await response.json();
                    slug.value = data.slug;
                    refreshSeo();
                }
            }

            function refreshSeo() {
                const html = currentTab === 'code' ? htmlEditor.value : getEditorContent();
                const text = plainText(html);
                const words = text ? (text.match(/[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/gu) || []).length : 0;
                const keyword = (focus.value || '').toLocaleLowerCase().trim();
                const titleText = (seoTitle.value || title.value || '').toLocaleLowerCase();
                const descText = (seoDescription.value || '').toLocaleLowerCase();
                const slugText = (slug.value || slugify(title.value)).toLocaleLowerCase();
                const keywordSlug = slugify(keyword);
                const checks = {
                    'keyword-title': Boolean(keyword && titleText.includes(keyword)),
                    'keyword-description': Boolean(keyword && descText.includes(keyword)),
                    'keyword-url': Boolean(keywordSlug && slugText.includes(keywordSlug)),
                    'keyword-content': Boolean(keyword && text.toLocaleLowerCase().includes(keyword)),
                    'content-length': words >= 600,
                    'description-length': seoDescription.value.length >= 120 && seoDescription.value.length <= 160,
                    'title-length': (seoTitle.value || title.value).length >= 35 && (seoTitle.value || title.value).length <= 65,
                    'featured-image': Boolean(document.getElementById('featured-image-url').value || document.getElementById('featured-image-id').value),
                    'category': Boolean(document.querySelector('input[name="category_id"]:checked')?.value),
                    'tags': selectedTags.length > 0,
                };
                const weights = {'keyword-title':15,'keyword-description':12,'keyword-url':10,'keyword-content':13,'content-length':15,'description-length':10,'title-length':10,'featured-image':7,'category':5,'tags':3};
                let score = 0;
                Object.entries(checks).forEach(([key, ok]) => {
                    const row = document.querySelector(`[data-check="${key}"]`);
                    if (row) {
                        row.dataset.state = ok ? 'good' : 'bad';
                        row.querySelector('.mark').textContent = ok ? '✓' : 'x';
                    }
                    if (ok) score += weights[key] || 0;
                });
                score = Math.min(100, score);
                const errors = Object.values(checks).filter(ok => !ok).length;
                scoreInline.textContent = score;
                scoreSidebar.textContent = score;
                errorPill.textContent = `${errors} Errors`;
                wordCount.textContent = words;
                previewTitle.textContent = seoTitle.value || title.value || 'News ART';
                previewUrl.textContent = `/blog/${slugText || 'post-slug'}`;
                previewDescription.textContent = seoDescription.value || document.querySelector('[name="excerpt"]').value || '';
            }

            function switchTab(tab) {
                if (tab === currentTab) return;
                const editor = window.tinymce ? tinymce.get('post-content') : null;
                const editorContainer = editor?.getContainer();
                if (tab === 'code') {
                    syncFromVisual();
                    htmlEditor.style.display = 'block';
                    content.style.display = 'none';
                    if (editorContainer) editorContainer.style.display = 'none';
                } else {
                    syncFromCode();
                    htmlEditor.style.display = 'none';
                    content.style.display = 'none';
                    if (editorContainer) editorContainer.style.display = '';
                }
                currentTab = tab;
                document.querySelectorAll('[data-editor-tab]').forEach(btn => btn.classList.toggle('active', btn.dataset.editorTab === tab));
            }

            function insertHtml(html) {
                if (currentTab === 'code') {
                    const start = htmlEditor.selectionStart || 0;
                    htmlEditor.value = htmlEditor.value.slice(0, start) + html + htmlEditor.value.slice(start);
                    syncFromCode();
                } else if (window.tinymce && tinymce.get('post-content')) {
                    tinymce.get('post-content').insertContent(html);
                    syncFromVisual();
                }
                refreshSeo();
            }

            async function autosave() {
                if (!postId) return;
                syncFromVisual();
                const response = await fetch(routes.autosave, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
                    body: JSON.stringify({
                        post_id: postId,
                        title: title.value,
                        slug: slug.value,
                        excerpt: document.querySelector('[name="excerpt"]').value,
                        content: content.value,
                        seo_title: seoTitle.value,
                        seo_description: seoDescription.value,
                        focus_keyword: focus.value
                    })
                }).catch(() => {});
                if (response && response.ok) {
                    const data = await response.json().catch(() => ({}));
                    if (data.post_id) postId = data.post_id;
                }
            }

            function openMedia(mode) {
                mediaMode = mode || 'insert';
                selectedMedia = null;
                updateMediaAction();
                activateMediaTab('upload');
                document.getElementById('media-modal').classList.add('is-open');
            }

            function closeMedia() {
                document.getElementById('media-modal').classList.remove('is-open');
            }

            function selectMedia(tile) {
                document.querySelectorAll('.media-tile').forEach(item => item.classList.remove('is-selected'));
                tile.classList.add('is-selected');
                selectedMedia = {
                    url: tile.dataset.url,
                    title: tile.dataset.title || '',
                    alt: tile.dataset.alt || '',
                    caption: tile.dataset.caption || '',
                    image: tile.dataset.image === '1'
                };
                updateMediaAction();
            }

            function updateMediaAction() {
                const details = document.getElementById('selected-media-details');
                const button = document.getElementById('media-use');
                button.disabled = !selectedMedia;
                button.textContent = mediaMode === 'featured' ? 'Set Featured Image' : 'Insert into editor';

                if (!selectedMedia) {
                    details.textContent = 'Select or upload an image.';
                    return;
                }

                details.innerHTML = `<img src="${selectedMedia.url}" alt=""><span><strong>${selectedMedia.title || 'Image'}</strong><br>${selectedMedia.url}</span>`;
            }

            function activateMediaTab(activeTab) {
                document.querySelectorAll('[data-media-tab]').forEach(tab => tab.classList.toggle('is-active', tab.dataset.mediaTab === activeTab));
                document.querySelectorAll('[data-media-panel]').forEach(panel => {
                    panel.hidden = panel.dataset.mediaPanel !== activeTab;
                });
                if (activeTab === 'library') {
                    loadMediaLibrary();
                }
            }

            function addMediaTile(media) {
                const library = document.getElementById('media-library');
                const help = library.querySelector('.wp-help');
                if (help) help.remove();
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'media-tile';
                button.dataset.url = media.url;
                button.dataset.title = media.title || '';
                button.dataset.alt = media.alt_text || '';
                button.dataset.caption = media.caption || '';
                button.dataset.image = '1';
                button.innerHTML = `<img src="${media.url}" alt="${media.alt_text || ''}"><small>${media.title || media.name || 'Image'}</small>`;
                button.addEventListener('click', () => selectMedia(button));
                library.prepend(button);
                selectMedia(button);
                activateMediaTab('library');
            }

            async function loadMediaLibrary() {
                if (mediaLibraryLoaded || mediaLibraryLoading) return;
                const library = document.getElementById('media-library');
                mediaLibraryLoading = true;
                library.innerHTML = '<p class="wp-help">Loading media...</p>';

                try {
                    const response = await fetch(routes.mediaIndex, {headers: {'Accept': 'application/json'}});
                    if (!response.ok) throw new Error('media-index-failed');
                    const data = await response.json();
                    library.innerHTML = '';
                    (data.items || []).forEach(item => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'media-tile';
                        button.dataset.url = item.url;
                        button.dataset.title = item.title || item.name || '';
                        button.dataset.alt = item.alt_text || '';
                        button.dataset.caption = item.caption || '';
                        button.dataset.image = '1';
                        button.innerHTML = `<img src="${item.url}" alt="${item.alt_text || ''}"><small>${item.title || item.name || 'Image'}</small>`;
                        button.addEventListener('click', () => selectMedia(button));
                        library.appendChild(button);
                    });
                    if (!library.children.length) {
                        library.innerHTML = '<p class="wp-help">No images are available in the media library yet.</p>';
                    }
                    mediaLibraryLoaded = true;
                } catch (error) {
                    library.innerHTML = '<p class="wp-help">Media library could not be loaded. Upload a new image or try again.</p>';
                } finally {
                    mediaLibraryLoading = false;
                }
            }

            async function uploadMedia() {
                const file = document.getElementById('media-file').files[0];
                if (!file) return;
                const status = document.getElementById('media-upload-status');
                status.textContent = 'Uploading...';
                const form = new FormData();
                form.append('image', file);
                form.append('title', file.name.replace(/\.[^.]+$/, ''));
                const response = await fetch(routes.mediaStore, {method:'POST', headers:{'X-CSRF-TOKEN':csrf, 'Accept':'application/json'}, body:form});
                if (response.ok) {
                    const data = await response.json();
                    addMediaTile(data.media);
                    mediaLibraryLoaded = false;
                    status.textContent = 'Uploaded. Use the image or choose another one.';
                } else {
                    let message = 'Upload failed. Please choose a valid image up to 4 MB.';
                    try {
                        const data = await response.json();
                        message = data.message || Object.values(data.errors || {})[0]?.[0] || message;
                    } catch (error) {
                        //
                    }
                    status.textContent = message;
                }
            }

            function setFeatured() {
                if (!selectedMedia) return;
                document.getElementById('featured-image-id').value = '';
                document.getElementById('featured-image-url').value = selectedMedia.url;
                document.getElementById('featured-image-alt').value = selectedMedia.alt;
                document.getElementById('featured-preview').innerHTML = selectedMedia.image ? `<img src="${selectedMedia.url}" alt="" style="max-width:100%;display:block;">` : `<a href="${selectedMedia.url}">${selectedMedia.title || selectedMedia.url}</a>`;
                refreshSeo();
                closeMedia();
            }

            function useSelectedMedia() {
                if (!selectedMedia) return;
                if (mediaMode === 'featured') {
                    setFeatured();
                    return;
                }

                insertHtml(`<figure><img src="${selectedMedia.url}" alt="${selectedMedia.alt || ''}">${selectedMedia.caption ? `<figcaption>${selectedMedia.caption}</figcaption>` : ''}</figure>`);
                closeMedia();
            }

            async function quickCategory() {
                const input = document.getElementById('new-category-name');
                const status = document.getElementById('category-quick-status');
                if (!input.value.trim()) return;
                status.classList.remove('is-error');
                status.textContent = 'Adding category…';
                try {
                    const response = await fetch(routes.categoryQuick, {
                        method:'POST',
                        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                        body:JSON.stringify({name: input.value})
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Category could not be created.');
                    const label = document.createElement('label');
                    const radio = document.createElement('input');
                    radio.type = 'radio'; radio.name = 'category_id'; radio.value = data.category.id; radio.checked = true;
                    label.append(radio, document.createTextNode(` ${data.category.name}`));
                    document.getElementById('category-list').appendChild(label);
                    input.value = '';
                    status.textContent = 'Category added and selected.';
                    refreshSeo();
                } catch (error) {
                    status.classList.add('is-error');
                    status.textContent = error.message || 'Category could not be created.';
                }
            }

            function refreshVisibility() {
                passwordRow.style.display = visibility.value === 'password' ? 'block' : 'none';
            }

            function focusFirstError() {
                const target = document.querySelector('.wp-field-error') || document.querySelector('.wp-error-text');
                if (!target) return;
                const focusable = target.matches('input, textarea, select, button, a') ? target : target.querySelector('input, textarea, select, button, a');
                target.scrollIntoView({behavior: 'smooth', block: 'center'});
                setTimeout(() => {
                    if (target.id === 'field-content' && window.tinymce && tinymce.get('post-content')) {
                        tinymce.get('post-content').focus();
                    } else if (focusable) {
                        focusable.focus({preventScroll: true});
                    } else if (target.focus) {
                        target.focus({preventScroll: true});
                    }
                }, 350);
            }

            document.querySelectorAll('.wp-box-title').forEach(title => title.addEventListener('click', () => title.closest('.wp-box').classList.toggle('is-collapsed')));
            document.querySelectorAll('[data-error-link]').forEach(link => link.addEventListener('click', event => {
                event.preventDefault();
                const href = link.getAttribute('href');
                const fieldName = link.dataset.errorLink;
                let target = href ? document.querySelector(href) : null;
                if (!target && fieldName) {
                    target = document.querySelector(`[name="${fieldName}"]`) || document.getElementById(`field-${fieldName.replace(/[._]/g, '-')}`);
                }
                if (target) {
                    target.scrollIntoView({behavior:'smooth', block:'center'});
                    const field = target.matches('input, textarea, select') ? target : target.querySelector('input, textarea, select');
                    if (field) setTimeout(() => field.focus({preventScroll:true}), 250);
                }
            }));
            document.querySelectorAll('[data-editor-tab]').forEach(btn => btn.addEventListener('click', () => switchTab(btn.dataset.editorTab)));
            document.querySelectorAll('[data-media-open]').forEach(btn => btn.addEventListener('click', () => openMedia(btn.dataset.mediaOpen)));
            document.querySelectorAll('.media-tile').forEach(tile => tile.addEventListener('click', () => selectMedia(tile)));
            document.querySelectorAll('[data-media-tab]').forEach(tab => tab.addEventListener('click', () => activateMediaTab(tab.dataset.mediaTab)));
            document.querySelectorAll('[data-quicktag]').forEach(btn => btn.addEventListener('click', () => {
                const tag = btn.dataset.quicktag;
                const map = {b:['<strong>','</strong>'], i:['<em>','</em>'], blockquote:['<blockquote>','</blockquote>'], del:['<del>','</del>'], ins:['<ins>','</ins>'], ul:['<ul><li>','</li></ul>'], ol:['<ol><li>','</li></ol>'], li:['<li>','</li>'], code:['<code>','</code>'], img:['<img src="" alt="">',''], link:['<a href="">','</a>']};
                const pair = map[tag] || ['',''];
                insertHtml(pair[0] + pair[1]);
            }));
            document.querySelectorAll('input[name="template"]').forEach(input => input.addEventListener('change', () => document.getElementById('post-layout').value = input.value));
            document.getElementById('media-close').addEventListener('click', closeMedia);
            document.getElementById('media-select-file').addEventListener('click', () => document.getElementById('media-file').click());
            document.getElementById('media-file').addEventListener('change', uploadMedia);
            document.getElementById('media-use').addEventListener('click', useSelectedMedia);
            document.getElementById('add-category').addEventListener('click', quickCategory);
            document.getElementById('tag-picker').addEventListener('click', () => tagInput.focus());
            tagInput.addEventListener('input', showTagSuggestions);
            tagInput.addEventListener('focus', showTagSuggestions);
            tagInput.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ',') { event.preventDefault(); addTag(tagInput.value.replace(/,$/, '')); }
                if (event.key === 'Backspace' && !tagInput.value && selectedTags.length) { selectedTags.pop(); renderTagChips(); syncTagValue(); }
                if (event.key === 'Escape') tagSuggestions.classList.remove('is-open');
            });
            tagInput.addEventListener('blur', () => { if (tagInput.value.trim()) addTag(tagInput.value); setTimeout(() => tagSuggestions.classList.remove('is-open'), 120); });
            document.getElementById('generate-slug').addEventListener('click', generateSlug);
            visibility.addEventListener('change', refreshVisibility);
            title.addEventListener('blur', () => { if (!slug.value.trim()) generateSlug(); });
            [title, slug, focus, seoTitle, seoDescription, tags, htmlEditor, document.querySelector('[name="excerpt"]')].filter(Boolean).forEach(el => el.addEventListener('input', refreshSeo));
            document.querySelectorAll('input[name="category_id"]').forEach(input => input.addEventListener('change', refreshSeo));
            document.getElementById('post-form').addEventListener('submit', () => currentTab === 'code' ? syncFromCode() : syncFromVisual());
            renderTagChips();
            syncTagValue();

            if (window.tinymce) {
                tinymce.init({
                    selector: '#post-content',
                    height: 430,
                    menubar: false,
                    branding: false,
                    plugins: 'lists link image table code codesample fullscreen wordcount media',
                    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist blockquote | link image table codesample | code fullscreen',
                    block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6',
                    paste_as_text: false,
                    valid_elements: '*[*]',
                    extended_valid_elements: 'iframe[src|width|height|allowfullscreen|frameborder],script[src|type]',
                    setup: editor => {
                        editor.on('change input keyup undo redo', () => {
                            syncFromVisual();
                            refreshSeo();
                        });
                    },
                    images_upload_handler: (blobInfo) => new Promise((resolve, reject) => {
                        const form = new FormData();
                        form.append('image', blobInfo.blob(), blobInfo.filename());
                        form.append('title', blobInfo.filename().replace(/\.[^.]+$/, ''));
                        fetch(routes.mediaStore, {method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}, body:form})
                            .then(response => response.ok ? response.json() : Promise.reject())
                            .then(data => { addMediaTile(data.media); resolve(data.media.url); })
                            .catch(() => reject('Upload failed'));
                    }),
                    init_instance_callback: () => {
                        syncFromVisual();
                        refreshSeo();
                    }
                });
            }

            refreshVisibility();
            refreshSeo();
            focusFirstError();
            setInterval(autosave, 60000);
        })();
    </script>
</x-app-layout>
