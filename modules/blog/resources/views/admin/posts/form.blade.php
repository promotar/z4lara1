@php
    $writerMode = $writerMode ?? false;
    $locale = $locale ?? 'en';
    $locales = $locales ?? [];
    $formLocale = $locale;
    $t = static fn (string $key, array $replace = []) => __($key, $replace, $formLocale);
    $isTranslation = ! \Modules\Blog\Support\BlogLocale::isDefault($locale);
@endphp
<x-dynamic-component :component="$writerMode ? 'frontend-layout' : 'app-layout'">
    @php
        $isEdit = $post->exists;
        $canPublishDirectly = $canPublishDirectly ?? true;
        $action = $formAction ?? ($isEdit ? route('admin.plugins.blog.posts.update', $post, false) : route('admin.plugins.blog.posts.store', [], false));
        $deleteAction = $deleteAction ?? ($isEdit ? route('admin.plugins.blog.posts.destroy', $post, false) : null);
        $editorRoutes = $editorRoutes ?? [
            'autosave' => route('admin.plugins.blog.posts.autosave', [], false),
            'slug' => route('admin.plugins.blog.posts.slug', [], false),
            'mediaIndex' => route('admin.media.index', [], false),
            'mediaStore' => route('admin.media.store', [], false),
            'categoryQuick' => route('admin.plugins.blog.categories.quick-store', [], false),
        ];
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
        // "View public post" must open the tab's language and switch the
        // visitor's site language to that locale (session), not the base one.
        $previewLocale = $locale;
        $previewSlug = $translation?->slug ?: $basePost->slug;
        $publicPostPath = $isEdit && $previewSlug ? '/blog/'.$previewSlug : null;
        $previewSwitchPath = $publicPostPath
            ? route('locale.switch', ['locale' => $previewLocale], false).'?to='.urlencode($publicPostPath)
            : null;
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
        .wp-permalink { margin:8px 0 16px; color:var(--ainpa-admin-text, #50575e); font-size:13px; }
        .wp-permalink input { width:220px; border:1px solid #c3c4c7; padding:4px 6px; }
        .wp-builder-row { margin: 22px 0; }
        .wp-blue-btn { background:#3858e9; border:1px solid #3858e9; color:#fff; padding:11px 28px; font-weight:600; cursor:pointer; }
        .wp-toolbar-row { display:flex; justify-content:space-between; align-items:end; margin-top:10px; }
        .wp-media-actions { display:flex; gap:6px; }
        .wp-secondary, .wp-tab, .wp-quicktag { background:color-mix(in srgb, var(--ainpa-admin-text, #f6f7f7) 4%, transparent); border:1px solid var(--ainpa-admin-accent, #2271b1); color:var(--ainpa-admin-accent, #2271b1); cursor:pointer; padding:7px 10px; text-decoration:none; }
        .wp-tab { border-color:#c3c4c7; color:var(--ainpa-admin-text, #50575e); border-bottom:0; background:color-mix(in srgb, var(--ainpa-admin-text, #f6f7f7) 4%, transparent); }
        .wp-tab.active { background:#fff; color:#1d2327; }
        .wp-editor-wrap { border:1px solid #c3c4c7; background:#fff; }
        .wp-quicktags { border-bottom:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); padding:6px; background:color-mix(in srgb, var(--ainpa-admin-text, #f6f7f7) 4%, transparent); display:flex; flex-wrap:wrap; gap:4px; }
        .wp-quicktag { padding:4px 8px; border-color:#8c8f94; color:#1d2327; font-size:12px; }
        #post-content { min-height:360px; }
        #html-editor { display:none; width:100%; min-height:430px; border:0; padding:12px; font-family:Consolas, monospace; font-size:13px; resize:vertical; }
        .wp-word-count { border-top:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); color:var(--ainpa-admin-text, #50575e); padding:6px 8px; font-size:12px; }
        .wp-box { background:#fff; border:1px solid #c3c4c7; margin-bottom:14px; }
        .wp-box-title { margin:0; padding:9px 12px; border-bottom:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); font-size:14px; font-weight:600; display:flex; justify-content:space-between; align-items:center; cursor:pointer; }
        .wp-box-body { padding:12px; }
        .wp-box.is-collapsed .wp-box-body { display:none; }
        .wp-input, .wp-select, .wp-textarea { width:100%; border:1px solid #8c8f94; background:#fff; padding:6px 8px; min-height:32px; }
        .wp-textarea { resize:vertical; min-height:64px; }
        .wp-help { color:#646970; font-size:12px; line-height:1.5; margin-top:6px; }
        .wp-publish-actions { display:flex; justify-content:space-between; gap:8px; margin-bottom:12px; }
        .wp-status-line { margin:10px 0; font-size:13px; }
        .wp-side-submit { background:color-mix(in srgb, var(--ainpa-admin-text, #f6f7f7) 4%, transparent); border-top:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); margin:12px -12px -12px; padding:10px 12px; text-align:right; }
        .wp-primary { background:var(--ainpa-admin-accent, #2271b1); border:1px solid var(--ainpa-admin-accent, #2271b1); color:#fff; cursor:pointer; padding:7px 14px; font-weight:600; }
        .wp-danger { background:#fff; border:1px solid #d63638; color:#d63638; cursor:pointer; padding:6px 10px; }
        .wp-seo-mini { background:#fce8e8; color:#d63638; margin:10px -12px; padding:10px 12px; font-weight:600; }
        .wp-cat-list { max-height:170px; overflow:auto; border:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); padding:8px; }
        .wp-cat-list label { display:block; margin:4px 0; font-size:13px; }
        .wp-inline-status { min-height:17px; margin-top:6px; color:#646970; font-size:11px; }
        .wp-inline-status.is-error { color:#b91c1c; }
        .wp-tag-picker { position:relative; display:flex; flex-wrap:wrap; align-items:center; gap:6px; min-height:40px; border:1px solid #8c8f94; background:#fff; padding:5px 7px; cursor:text; }
        .wp-tag-picker:focus-within { border-color:var(--ainpa-admin-accent, #2271b1); box-shadow:0 0 0 1px var(--ainpa-admin-accent, #2271b1); }
        .wp-tag-chip { display:inline-flex; align-items:center; gap:5px; max-width:100%; border-radius:999px; background:color-mix(in srgb, var(--ainpa-admin-accent, #8b0000) 78%, #000); padding:4px 8px 4px 10px; color:#fff; font-size:12px; font-weight:700; }
        .wp-tag-chip span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .wp-tag-chip button { border:0; background:transparent; color:#fff; padding:0; font-size:15px; line-height:1; cursor:pointer; }
        .wp-tag-entry { flex:1 1 110px; min-width:90px; border:0 !important; outline:0 !important; box-shadow:none !important; padding:4px 2px; }
        .wp-tag-suggestions { position:absolute; z-index:30; top:calc(100% + 5px); left:0; right:0; display:none; max-height:180px; overflow:auto; border:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); background:#fff; box-shadow:0 10px 25px rgba(15,23,42,.14); }
        .wp-tag-suggestions.is-open { display:block; }
        .wp-tag-suggestion { display:flex; justify-content:space-between; width:100%; border:0; border-bottom:1px solid #f0f0f1; background:#fff; padding:8px 10px; text-align:left; cursor:pointer; }
        .wp-tag-suggestion:hover,.wp-tag-suggestion:focus { background:#fff1f2; color:color-mix(in srgb, var(--ainpa-admin-accent, #8b0000) 78%, #000); }
        .seo-preview { padding:14px 12px; border-bottom:1px solid #f0f0f1; }
        .seo-preview-title { color:#1a0dab; font-size:16px; margin-bottom:4px; }
        .seo-preview-url { color:#006621; font-size:12px; word-break:break-all; }
        .seo-preview-description { color:#4d5156; font-size:13px; margin-top:4px; }
        .seo-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .rank-score-row { display:grid; grid-template-columns:1fr 80px; gap:0; align-items:center; }
        .rank-score-cell { background:#fce8e8; color:#d63638; border:1px solid #f3c5c5; border-left:0; padding:7px; text-align:center; font-weight:600; }
        .rank-section { border-top:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); padding:12px; }
        .rank-pill { background:#f4a6a6; color:#fff; border-radius:12px; font-size:11px; padding:2px 8px; margin-left:6px; }
        .rank-check { display:flex; align-items:center; gap:8px; margin:8px 0; color:var(--ainpa-admin-text, #50575e); font-size:13px; }
        .rank-check .mark { width:14px; height:14px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:10px; background:#d63638; }
        .rank-check[data-state="good"] .mark { background:#00a32a; }
        .layout-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
        .layout-option { border:2px solid transparent; cursor:pointer; display:block; padding:6px; }
        .layout-option:has(input:checked) { border-color:var(--ainpa-admin-accent, #2271b1); }
        .layout-option input { margin-bottom:5px; }
        .layout-thumb { height:95px; border:1px solid #ccd0d4; background:#fff; padding:8px; }
        .layout-thumb .line { height:6px; background:color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); margin-bottom:6px; }
        .layout-thumb .hero { height:35px; background:#a7c7e7; margin-bottom:6px; }
        .media-modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,.58); z-index:1000; }
        .media-modal.is-open { display:flex; }
        .media-panel { width:min(980px, 92vw); height:min(780px, calc(100dvh - 28px)); max-height:calc(100dvh - 28px); background:#fff; border:1px solid #1d2327; display:grid; grid-template-rows:auto auto minmax(0,1fr) auto; overflow:hidden; }
        .media-head, .media-foot { padding:12px 16px; border-bottom:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); display:flex; justify-content:space-between; align-items:center; }
        .media-foot { border-top:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); border-bottom:0; background:#fff; position:sticky; bottom:0; z-index:2; box-shadow:0 -8px 18px rgba(17,24,39,.07); }
        .media-tabs { display:flex; gap:0; border-bottom:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); padding:0 16px; background:color-mix(in srgb, var(--ainpa-admin-text, #f6f7f7) 4%, transparent); }
        .media-tabs button { border:0; border-right:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); background:transparent; cursor:pointer; padding:10px 14px; }
        .media-tabs button.is-active { background:#fff; font-weight:600; }
        .media-body { min-height:0; overflow:auto; }
        .media-panel-view { padding:18px; }
        .media-upload-drop { border:2px dashed #c3c4c7; min-height:245px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; text-align:center; background:#fbfbfc; }
        .media-upload-status { color:var(--ainpa-admin-text, #50575e); min-height:20px; }
        .media-library { overflow:auto; display:grid; grid-template-columns:repeat(auto-fill, minmax(115px, 1fr)); gap:10px; align-content:start; }
        .media-tile { border:2px solid transparent; background:color-mix(in srgb, var(--ainpa-admin-text, #f6f7f7) 4%, transparent); min-height:100px; cursor:pointer; padding:5px; text-align:center; overflow:hidden; }
        .media-tile.is-selected { border-color:var(--ainpa-admin-accent, #2271b1); }
        .media-tile img { width:100%; height:86px; object-fit:cover; display:block; margin-bottom:4px; }
        .media-selected-preview { display:flex; align-items:center; gap:10px; color:var(--ainpa-admin-text, #50575e); font-size:13px; }
        .media-selected-preview img { width:48px; height:48px; object-fit:cover; border:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); }
        .revisions-list { max-height:210px; overflow:auto; }
        .revision-row { border-top:1px solid color-mix(in srgb, var(--ainpa-admin-text, #dcdcde) 14%, transparent); padding:8px 0; display:flex; justify-content:space-between; gap:8px; align-items:center; }
        @media (max-width: 980px) { .wp-grid { grid-template-columns:1fr; } .wp-side-column { order:-1; } .layout-grid { grid-template-columns:1fr 1fr; } }
    </style>
    <style>
        body{background:#fffaf9}
        .wp-post-screen{width:100%;max-width:none;margin:0;background:#fffaf9;padding:26px 30px 32px;color:var(--ainpa-admin-text, #1f2937);font-family:Inter,Arial,sans-serif}
        .wp-post-screen>form,.wp-heading,.wp-notice{width:100%}
        .wp-heading{margin:0 0 13px;color:#111827;font-size:25px;font-weight:800}
        .wp-grid{grid-template-columns:minmax(0,1fr) 286px;gap:22px;align-items:start}
        .wp-grid>main{min-width:0}
        .wp-title-input{height:48px;border:1px solid #d8caca;border-radius:6px;padding:8px 13px;font-size:20px;box-shadow:0 1px 2px rgba(127,29,29,.03)}
        .wp-title-input::placeholder{color:#9ca3af}
        .wp-permalink{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:10px 0 16px;color:#4b5563;font-size:12px}
        .wp-permalink input{width:220px;min-height:34px;border-color:#decaca;border-radius:6px;background:#fff}
        .wp-secondary,.wp-tab,.wp-quicktag{border-color:#ee857c;border-radius:5px;background:#fff;color:#c3261b}
        .wp-secondary:hover,.wp-tab.active{border-color:#d92d20;background:#fff5f4;color:var(--ainpa-admin-accent, #a90000)}
        .wp-toolbar-row{margin-top:0}
        .wp-media-actions{padding-bottom:7px}
        .wp-editor-wrap{overflow:hidden;border-color:#dfcece;border-radius:8px;background:#fff;box-shadow:0 2px 10px rgba(127,29,29,.035)}
        .wp-quicktags{padding:6px 8px;border-color:#eadada;background:#fff}
        .wp-quicktag{min-width:30px;border-color:#efd2cf;background:#fff;color:#374151;font-weight:700}
        .wp-editor-wrap .tox-tinymce{min-height:410px!important;border:0!important;border-radius:0!important}
        #post-content{min-height:410px}
        .wp-word-count{border-color:#eadada;padding:7px 10px;background:#fff;color:var(--ainpa-admin-muted, #64748b)}
        .wp-box{overflow:hidden;margin:12px 0;border:1px solid #ead7d7;border-radius:8px;background:#fff;box-shadow:0 2px 10px rgba(127,29,29,.03)}
        .wp-box-title{min-height:42px;padding:10px 13px;border-color:#efdfdf;background:#fff;font-size:13px;font-weight:800}
        .wp-box-body{padding:13px}
        .wp-input,.wp-select,.wp-textarea{min-height:36px;border-color:#decaca;border-radius:5px;padding:7px 9px}
        .wp-textarea{min-height:76px}
        .wp-side-column{position:sticky;top:14px}
        .wp-side-column .wp-box{margin-top:0;margin-bottom:12px;box-shadow:0 4px 14px rgba(30,41,59,.07)}
        .wp-publish-actions{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:8px;align-items:stretch;margin-bottom:14px}
        .wp-publish-actions>*{display:inline-flex;min-width:0;height:42px;align-items:center;justify-content:center;padding:0 9px;font-size:12px;line-height:1.2;white-space:nowrap}
        .wp-status-line{display:grid;grid-template-columns:1fr;gap:5px;margin:12px 0;font-size:11px;font-weight:700}
        .wp-primary{min-height:36px;border:1px solid #d92d20;border-radius:5px;background:#d92d20;padding:7px 15px;color:#fff}
        .wp-primary:hover{background:#b91c1c}
        .wp-danger{min-height:35px;border-color:#e36b62;border-radius:5px;color:#c3261b}
        .wp-side-submit{display:flex;gap:7px;margin:13px -13px -13px;padding:8px 12px;border-color:#efdfdf;background:#fff}
        .wp-side-submit>*{display:inline-flex;min-height:40px;align-items:center;justify-content:center;flex:1;padding:0 12px;text-align:center;line-height:1.2}
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
        .media-upload-drop{border-color:#dfb8b8;border-radius:8px;background:color-mix(in srgb, var(--ainpa-admin-accent, #fffafa) 5%, #fff)}
        .wp-lang-tabs{display:flex;flex-wrap:wrap;gap:6px;margin:12px 0 0}
        .wp-lang-tabs a{display:inline-flex;align-items:center;gap:6px;border:1px solid #e3d6d6;border-radius:6px 6px 0 0;background:#fff;padding:8px 14px;color:#50575e;font-size:12px;font-weight:700;text-decoration:none}
        .wp-lang-tabs a.is-active{border-color:#e34237!important;background:#e34237!important;color:#fff!important}
        .wp-lang-tabs a .wp-lang-state{font-size:10px;opacity:.75}
        .wp-lang-note{margin:8px 0 0;border:1px solid #f0dada;border-radius:6px;background:#fff8f7;padding:8px 12px;color:#8a4a44;font-size:11px}
        @media(max-width:1100px){.wp-grid{grid-template-columns:minmax(0,1fr) 270px}.seo-settings-grid{grid-template-columns:1fr}.rank-section{grid-template-columns:1fr}}
        @media(max-width:900px){.wp-grid{grid-template-columns:1fr}.wp-side-column{position:static}.wp-publish-actions{max-width:380px}.wp-side-submit{justify-content:flex-start}.wp-side-submit>*{max-width:380px}.layout-grid{grid-template-columns:1fr 1fr}}
        @media(max-width:640px){.wp-post-screen{padding:16px 10px 24px}.wp-heading{font-size:21px}.wp-title-input{height:45px;font-size:18px}.wp-permalink input{width:100%}.wp-publish-actions{grid-template-columns:1fr;max-width:none;gap:8px}.wp-publish-actions>*{width:100%;height:auto;min-height:46px;padding:11px 14px;white-space:normal}.wp-side-submit{padding:10px 12px}.wp-side-submit>*{width:100%;max-width:none;min-height:46px}.seo-advanced,.seo-analysis{display:block;width:100%}.seo-advanced-grid,.layout-grid{grid-template-columns:1fr}.seo-advanced-grid>div{grid-column:auto;flex-direction:column}.wp-bottom-actions{align-items:stretch;flex-direction:column}.wp-bottom-actions>div>*{flex:1}}
    </style>

    <div class="wp-post-screen">
        @if($writerMode)<div class="wp-permalink"><a class="wp-secondary" href="{{ route('blog.writer.index', [], false) }}">{{ $t('blog::ui.auto_923f600e6a') }}</a><span>{{ $canPublishDirectly ? __('blog::ui.direct-publishing-enabled') : __('blog::ui.editorial-review-required') }}</span></div>@endif
        <h1 class="wp-heading">{{ $isEdit ? $t('blog::ui.admin_post_edit') : ($writerMode ? $t('blog::ui.admin_post_write') : $t('blog::ui.admin_post_add')) }}</h1>

        @if (count($locales) > 1)
            <div class="wp-lang-tabs">
                @foreach ($locales as $code => $name)
                    @php
                        $tabPost = $basePost ?? $post;
                        $tabTranslation = $isEdit ? $tabPost->translations->firstWhere('locale', $code) : null;
                        $editPost = $tabPost;
                    @endphp
                    <a href="{{ $isEdit ? (($writerMode ? route('blog.writer.posts.edit', $editPost, false) : route('admin.plugins.blog.posts.edit', $editPost, false)).'?locale='.urlencode($code)) : '#' }}"
                       @if(! $isEdit) data-blog-create-locale="{{ $code }}" @endif
                       class="{{ $locale === $code ? 'is-active' : '' }}"
                       @if($locale === $code) style="background:#e34237!important;color:#fff!important;border-color:#e34237!important" @endif>
                        {{ $name }}
                        <span class="wp-lang-state">{{ $code === \Modules\Blog\Support\BlogLocale::default() ? '' : ($tabTranslation ? '✓' : '+') }}</span>
                    </a>
                @endforeach
            </div>
            @if ($isTranslation)
                <p class="wp-lang-note">{{ $t('core.content.translation_note') }}</p>
            @endif
        @endif

        @if (session('status'))
            <div class="wp-notice">{{ session('status') }}</div>
        @endif

        @if (isset($errors) && $errors->any())
            <div class="wp-notice error">
                <strong>{{ $t('blog::ui.auto_79fa817c78') }}</strong>
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
            <input type="hidden" name="switch_locale" value="" data-blog-switch-locale>
            <input type="hidden" name="intent" value="draft" data-blog-switch-intent disabled>

            <input type="hidden" name="featured_image_id" id="featured-image-id" value="{{ $selectedFeaturedId ?: '' }}">
            <input type="hidden" name="featured_image" id="featured-image-url" value="{{ $featuredUrl }}">
            <input type="hidden" name="locale" value="{{ $locale }}">

            <div class="wp-grid">
                <main>
                    <input id="post-title" class="wp-title-input{{ $errorClass('title') }}" name="title" value="{{ old('title', $post->title) }}" placeholder="{{ $t('blog::ui.auto_53e5ca10b3') }}" required aria-describedby="{{ $hasError('title') ? $errorId('title') : '' }}">
                    @error('title')
                        <div class="wp-error-text" id="{{ $errorId('title') }}">{{ $message }}</div>
                    @enderror
                    <div class="wp-permalink">
                        {{ $t('blog::ui.auto_1753ee659d') }}
                        <span>{{ $t('blog::ui.auto_f7aa5e7d81') }}</span>
                        <input id="post-slug" class="{{ trim($errorClass('slug')) }}" name="slug" value="{{ old('slug', $post->slug) }}" placeholder="{{ $t('blog::ui.auto_d4b0437c75') }}" aria-describedby="{{ $hasError('slug') ? $errorId('slug') : '' }}">
                        <button class="wp-secondary" type="button" id="generate-slug">{{ $t('blog::ui.auto_6ebb5dfa46') }}</button>
                        @if ($isPubliclyViewable)
                            <a class="wp-secondary" href="{{ $previewSwitchPath }}" target="_blank" rel="noopener">{{ $t('blog::ui.auto_eb5586ad54') }}</a>
                        @endif
                        @error('slug')
                            <div class="wp-error-text" id="{{ $errorId('slug') }}">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="wp-builder-row" id="builder-row" style="display:none;">
                        <button class="wp-blue-btn" type="button">{{ $t('blog::ui.auto_8439963916') }}</button>
                    </div>

                    <div class="wp-toolbar-row">
                        <div class="wp-media-actions">
                            <button type="button" class="wp-secondary" data-media-open="insert">{{ $t('blog::ui.auto_980057390a') }}</button>
                        </div>
                        <div>
                            <button type="button" class="wp-tab active" data-editor-tab="visual">{{ $t('blog::ui.auto_4557e77491') }}</button>
                            <button type="button" class="wp-tab" data-editor-tab="code">{{ $t('blog::ui.auto_ca0dbad92a') }}</button>
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
                        <div class="wp-word-count">{{ $t('blog::ui.auto_b513f90b13') }} <span id="word-count">0</span></div>
                    </div>
                    @error('content')
                        <div class="wp-error-text" id="{{ $errorId('content') }}">{{ $message }}</div>
                    @enderror

                    <section class="wp-box">
                        <h2 class="wp-box-title">{{ $t('blog::ui.auto_6ca8bf4766') }} <span>^</span></h2>
                        <div class="wp-box-body">
                            <textarea id="field-excerpt" class="wp-textarea{{ $errorClass('excerpt') }}" name="excerpt">{{ old('excerpt', $post->excerpt) }}</textarea>
                            @error('excerpt')
                                <div class="wp-error-text" id="{{ $errorId('excerpt') }}">{{ $message }}</div>
                            @enderror
                            <div class="wp-help">{{ $t('blog::ui.auto_17c5883f50') }}</div>
                        </div>
                    </section>

                    <input type="hidden" name="schema_type" value="{{ old('schema_type', $post->schema_type ?: 'BlogPosting') }}">

                    <section class="wp-box">
                        <h2 class="wp-box-title">{{ $t('blog::ui.auto_1868678823') }} <span>^</span></h2>
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

                    @if ($isEdit && ! $writerMode && $post->revisions->isNotEmpty())
                        <section class="wp-box">
                            <h2 class="wp-box-title">{{ $t('blog::ui.auto_fd88b11cfd') }} <span>^</span></h2>
                            <div class="wp-box-body revisions-list">
                                @foreach ($post->revisions->take(4) as $revision)
                                    <div class="revision-row">
                                        <span>{{ $t('blog::ui.admin_revision_'.$revision->revision_type) }} - {{ $revision->created_at?->format('Y-m-d H:i') }} - {{ $revision->user?->name }}</span>
                                        <div style="display:flex;gap:6px;">
                                            <form method="POST" action="{{ route('admin.plugins.blog.posts.revisions.restore', [$post, $revision], false) }}">
                                                @csrf
                                                <button class="wp-secondary" type="submit">{{ $t('blog::ui.auto_2bd339d85e') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.plugins.blog.posts.revisions.destroy', [$post, $revision], false) }}" onsubmit="return confirm('Delete this revision?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="wp-danger" type="submit">{{ $t('blog::ui.auto_f2a6c498fb') }}</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                                <div class="wp-help">{{ $t('blog::ui.auto_760b9a6446') }}</div>
                            </div>
                        </section>
                    @endif
                </main>

                <aside class="wp-side-column">
                    <section class="wp-box">
                        <h2 class="wp-box-title">{{ $t('blog::ui.auto_110a4b01be') }} <span>^</span></h2>
                        <div class="wp-box-body">
                            <div class="wp-publish-actions">
                                <button class="wp-secondary" name="intent" value="draft" type="submit">{{ $t('blog::ui.auto_5778e182c5') }}</button>
                                <button class="wp-primary" name="intent" value="publish" type="submit">{{ $writerMode && ! $canPublishDirectly ? $t('blog::ui.admin_post_submit_review') : ($isEdit ? $t('blog::ui.admin_post_update') : $t('blog::ui.admin_post_publish')) }}</button>
                            </div>
                            @if($writerMode && ! $canPublishDirectly)
                                <input type="hidden" name="status" value="draft">
                                <div class="wp-status-line">{{ $t('blog::ui.auto_c474b62f24') }}<strong>{{ $post->review_status === 'pending' ? 'Pending editorial review' : 'Draft until submitted' }}</strong></div>
                            @else
                                <div class="wp-status-line">{{ $t('blog::ui.auto_24a23d7871') }}
                                    <select id="field-status" class="wp-select{{ $errorClass('status') }}" name="status">
                                        @foreach (['draft' => $t('blog::ui.admin_posts_draft'), 'published' => $t('blog::ui.admin_posts_published'), 'scheduled' => $t('blog::ui.admin_posts_scheduled'), 'private' => $t('blog::ui.admin_posts_private')] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('status', $post->status ?: 'draft') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('status')<div class="wp-error-text" id="{{ $errorId('status') }}">{{ $message }}</div>@enderror
                                </div>
                            @endif
                            <div class="wp-status-line">{{ $t('blog::ui.auto_8ad79b2107') }}
                                <select class="wp-select{{ $errorClass('visibility') }}" name="visibility" id="post-visibility">
                                    @foreach (['public' => $t('blog::ui.admin_visibility_public'), 'private' => $t('blog::ui.admin_visibility_private'), 'password' => $t('blog::ui.admin_visibility_password')] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('visibility', $post->visibility ?: 'public') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('visibility')<div class="wp-error-text" id="{{ $errorId('visibility') }}">{{ $message }}</div>@enderror
                            </div>
                            <div class="wp-status-line" id="password-row">{{ $t('blog::ui.auto_b341a59d56') }}
                                <input id="field-password" class="wp-input{{ $errorClass('password') }}" name="password" value="{{ old('password', $post->password) }}">
                                @error('password')<div class="wp-error-text" id="{{ $errorId('password') }}">{{ $message }}</div>@enderror
                            </div>
                            @if(! $writerMode || $canPublishDirectly)
                                <div class="wp-status-line">{{ $t('blog::ui.auto_cb656f0cf9') }}
                                    <input id="field-published-at" class="wp-input{{ $errorClass('published_at') }}" type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}">
                                    @error('published_at')<div class="wp-error-text" id="{{ $errorId('published_at') }}">{{ $message }}</div>@enderror
                                </div>
                                <div class="wp-status-line">{{ $t('blog::ui.auto_371086e2b1') }}
                                    <input id="field-scheduled-at" class="wp-input{{ $errorClass('scheduled_at') }}" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $post->scheduled_at?->format('Y-m-d\TH:i')) }}">
                                    @error('scheduled_at')<div class="wp-error-text" id="{{ $errorId('scheduled_at') }}">{{ $message }}</div>@enderror
                                </div>
                            @endif
                            <div class="wp-side-submit">
                                @if(! $writerMode || $canPublishDirectly)<button class="wp-secondary" name="intent" value="schedule" type="submit">{{ $t('blog::ui.auto_25f7347236') }}</button>@endif
                                @if ($isPubliclyViewable)<a class="wp-secondary" href="{{ $previewSwitchPath }}" target="_blank" rel="noopener">{{ $t('blog::ui.auto_b2e886db45') }}</a>@else<button class="wp-secondary" name="intent" value="preview" type="submit">{{ $t('blog::ui.auto_ba37ff7bc5') }}</button>@endif
                            </div>
                        </div>
                    </section>

                    <section class="wp-box">
                        <h2 class="wp-box-title">{{ $t('blog::ui.auto_af1b98adf7') }} <span>^</span></h2>
                        <div class="wp-box-body">
                            <div class="wp-cat-list" id="category-list">
                                <label><input type="radio" name="category_id" value="" @checked(! old('category_id', $post->category_id))> {{ $t('blog::ui.auto_0d015d96f6') }}</label>
                                @foreach ($categories as $category)
                                    <label><input type="radio" name="category_id" value="{{ $category->id }}" @checked((int) old('category_id', $post->category_id) === $category->id)> {{ $category->name }}</label>
                                @endforeach
                            </div>
                            @error('category_id')<div class="wp-error-text" id="{{ $errorId('category_id') }}">{{ $message }}</div>@enderror
                            @unless($writerMode)
                                <div style="display:flex; gap:6px; margin-top:8px;">
                                    <input id="new-category-name" class="wp-input" placeholder="{{ $t('blog::ui.auto_b9a492bdfa') }}">
                                    <button type="button" class="wp-secondary" id="add-category">{{ $t('blog::ui.auto_ec211f7c20') }}</button>
                                </div>
                                <div class="wp-inline-status" id="category-quick-status" role="status"></div>
                            @endunless
                        </div>
                    </section>

                    <section class="wp-box">
                        <h2 class="wp-box-title">{{ $t('blog::ui.auto_189f63f277') }} <span>^</span></h2>
                        <div class="wp-box-body">
                            <input id="post-tags" type="hidden" name="tags" value="{{ $selectedTags->implode(',') }}">
                            <div class="wp-tag-picker" id="tag-picker">
                                <div id="tag-chips" style="display:contents"></div>
                                <input id="post-tag-input" class="wp-tag-entry" autocomplete="off" placeholder="{{ $t('blog::ui.auto_ddc1aeb0fa') }}">
                                <div class="wp-tag-suggestions" id="tag-suggestions" role="listbox"></div>
                            </div>
                            <div class="wp-help">{{ $t('blog::ui.auto_a472003181') }}</div>
                        </div>
                    </section>

                    <section class="wp-box">
                        <h2 class="wp-box-title">{{ $t('blog::ui.auto_52beb68a92') }} <span>^</span></h2>
                        <div class="wp-box-body">
                            <button type="button" class="wp-secondary" data-media-open="featured">{{ $t('blog::ui.auto_e52099d4a7') }}</button>
                            <input class="wp-input{{ $errorClass('featured_image_alt') }}" style="margin-top:8px;" name="featured_image_alt" id="featured-image-alt" value="{{ $featuredAlt }}" placeholder="{{ $t('blog::ui.auto_390c85457d') }}">
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
            <div class="wp-bottom-actions">@if($isEdit)<button class="wp-danger" type="submit" form="delete-current-post">{{ $t('blog::ui.auto_7a289ea949') }}</button>@else<span></span>@endif<div><button class="wp-secondary" name="intent" value="draft" type="submit">{{ $t('blog::ui.auto_5778e182c5') }}</button><button class="wp-primary" name="intent" value="publish" type="submit">{{ $writerMode && ! $canPublishDirectly ? $t('blog::ui.admin_post_submit_review') : ($isEdit ? $t('blog::ui.admin_post_update') : $t('blog::ui.admin_post_publish')) }}</button></div></div>
        </form>

        @if ($isEdit)
            <form id="delete-current-post" class="wp-hidden-delete-form" method="POST" action="{{ $deleteAction }}">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>

    <div class="media-modal" id="media-modal" aria-hidden="true">
        <div class="media-panel">
            <div class="media-head"><strong>{{ $t('blog::ui.auto_f9d1d7210c') }}</strong><button class="wp-secondary" type="button" id="media-close">{{ $t('blog::ui.auto_d3d2e61733') }}</button></div>
            <div class="media-tabs">
                <button type="button" class="is-active" data-media-tab="upload">{{ $t('blog::ui.auto_c7de86f69d') }}</button>
                <button type="button" data-media-tab="library">{{ $t('blog::ui.auto_f9d1d7210c') }}</button>
            </div>
            <div class="media-body">
                <div class="media-panel-view" data-media-panel="upload">
                    <div class="media-upload-drop">
                        <strong>{{ $t('blog::ui.auto_cc27bdc4fd') }}</strong>
                        <span>or</span>
                        <button type="button" class="wp-secondary" id="media-select-file">{{ $t('blog::ui.auto_b51000000f') }}</button>
                        <input type="file" id="media-file" accept=".png,.jpg,.jpeg,.webp,.gif,.ico" hidden>
                        <div class="media-upload-status" id="media-upload-status">{{ $t('blog::ui.auto_4cc56c653e') }}</div>
                    </div>
                </div>
                <div class="media-panel-view" data-media-panel="library" hidden>
                    <div class="media-library" id="media-library">
                        <p class="wp-help">{{ $t('blog::ui.auto_0da2877535') }}</p>
                    </div>
                </div>
            </div>
            <div class="media-foot">
                <div class="media-selected-preview" id="selected-media-details">{{ $t('blog::ui.auto_29f837d883') }}</div>
                <div>
                    <button class="wp-primary" type="button" id="media-use" disabled>{{ $t('blog::ui.auto_5d7864737d') }}</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        (() => {
            const csrf = @json(csrf_token());
            const routes = {
                autosave: @json($editorRoutes['autosave']),
                slug: @json($editorRoutes['slug']),
                mediaIndex: @json($editorRoutes['mediaIndex']),
                mediaStore: @json($editorRoutes['mediaStore']),
                categoryQuick: @json($editorRoutes['categoryQuick']),
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
            const tags = document.getElementById('post-tags');
            const tagInput = document.getElementById('post-tag-input');
            const tagChips = document.getElementById('tag-chips');
            const tagSuggestions = document.getElementById('tag-suggestions');
            const availableTags = @json($availableTags->map(fn ($tag) => ['name' => $tag->name, 'slug' => $tag->slug])->values());
            let selectedTags = @json($selectedTags);
            const wordCount = document.getElementById('word-count');
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
                const words = text ? (text.match(/[\p{L}\p{N}]+(?:['’T\-][\p{L}\p{N}]+)*/gu) || []).length : 0;
                wordCount.textContent = words;
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
                        seo_title: document.querySelector('[name="seo_title"]')?.value || '',
                        seo_description: document.querySelector('[name="seo_description"]')?.value || '',
                        focus_keyword: document.querySelector('[name="focus_keyword"]')?.value || ''
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
                button.textContent = mediaMode === 'featured' ? 'Set featured image' : 'Insert image into post';

                if (!selectedMedia) {
                    details.textContent = mediaMode === 'featured' ? 'Pick an image, then press “Set featured image”.' : 'Pick an image (or upload one), then press “Insert image into post”.';
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
                button.addEventListener('dblclick', () => { selectMedia(button); useSelectedMedia(); });
                button.title = 'Click to select — double-click to insert';
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
                        button.addEventListener('dblclick', () => { selectMedia(button); useSelectedMedia(); });
                        button.title = 'Click to select — double-click to insert';
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
            document.querySelectorAll('.media-tile').forEach(tile => {
                tile.addEventListener('click', () => selectMedia(tile));
                tile.addEventListener('dblclick', () => { selectMedia(tile); useSelectedMedia(); });
                tile.title = 'Click to select — double-click to insert';
            });
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
            document.getElementById('add-category')?.addEventListener('click', quickCategory);
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
            [title, slug, tags, htmlEditor, document.querySelector('[name="excerpt"]')].filter(Boolean).forEach(el => el.addEventListener('input', refreshSeo));
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
                    // Use the browser's native context menu (Copy/Paste/Spellcheck…).
                    contextmenu: false,
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

    @if (! $isEdit)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('post-form');
            document.querySelectorAll('[data-blog-create-locale]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    var locale = link.dataset.blogCreateLocale;
                    if (locale === @json($locale)) { return; }
                    var title = document.getElementById('post-title');
                    if (!title || !title.value.trim()) {
                        alert(@json($t('blog::ui.admin_post_title_required')));
                        return;
                    }
                    form.querySelector('[data-blog-switch-locale]').value = locale;
                    form.querySelector('[data-blog-switch-intent]').disabled = false;
                    form.requestSubmit();
                });
            });
        });
    </script>
    @endif
</x-dynamic-component>
