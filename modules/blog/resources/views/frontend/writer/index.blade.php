@push('styles')@vite('resources/css/account.css')@endpush
<x-frontend-layout>
    <x-slot name="head">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <title>{{ __('blog::ui.my-blog-posts') }}</title>
    </x-slot>
    <style>
        .ainpa-account-tabs a{display:flex;width:100%;min-height:2.85rem;gap:.65rem;align-items:center;padding:.65rem .8rem;border:1px solid transparent;border-radius:.7rem;background:transparent;color:#f6dadd;font:inherit;font-size:.88rem;font-weight:700;text-align:start;cursor:pointer;text-decoration:none}
        .ainpa-account-tabs a:hover{background:rgb(255 255 255 / 7%);color:#fff}
        .ainpa-account-tabs a.is-active{border-color:rgb(255 255 255 / 20%);background:#fff;color:var(--account-deep)}
        .ainpa-account-tabs a b{margin-inline-start:auto;padding:.18rem .42rem;border-radius:999px;background:rgb(255 255 255 / 13%);font-size:.68rem}
        .ainpa-account-tabs a.is-active b{background:var(--account-soft)}
        .ainpa-account-subnav{display:grid;gap:.2rem;margin:.15rem 0 .4rem;padding-inline-start:.85rem;border-inline-start:1px solid rgb(255 255 255 / 20%)}
        .ainpa-account-subnav a{display:block;padding:.34rem .5rem;border-radius:.5rem;color:#f0c9cc;font-size:.78rem;font-weight:700;text-decoration:none}
        .ainpa-account-subnav a:hover{background:rgb(255 255 255 / 8%);color:#fff}
        .ainpa-account-subnav a.is-active{background:rgb(255 255 255 / 18%);color:#fff}
        .writer-dashboard{max-width:100%;margin:0;padding:0;color:var(--art-color-text, #1f2937)}
        .writer-dashboard__head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px}
        .writer-dashboard__head h1{margin:0;font-size:28px}
        .writer-dashboard__head p{margin:6px 0 0;color:var(--art-color-muted, #6b7280)}
        .writer-dashboard__button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;border:1px solid var(--art-color-accent, #a90000);border-radius:8px;background:var(--art-color-accent, #a90000);padding:0 17px;color:#fff !important;font-size:13px;font-weight:800;text-decoration:none}
        .writer-dashboard__notice{margin-bottom:13px;border-left:4px solid #15803d;border-radius:7px;background:#ecfdf5;padding:10px 13px;color:#166534}
        .writer-posts{overflow:hidden;border:1px solid color-mix(in srgb, var(--art-color-text, #ead5d5) 14%, transparent);border-radius:12px;background:var(--art-color-surface, #fff)}
        .writer-post{display:grid;grid-template-columns:minmax(210px,1fr) 140px 150px auto;gap:12px;align-items:center;padding:15px 17px;border-bottom:1px solid color-mix(in srgb, var(--art-color-text, #f0e1e1) 9%, transparent)}
        .writer-post:last-child{border-bottom:0}
        .writer-post strong,.writer-post small{display:block}
        .writer-post small{margin-top:4px;color:var(--art-color-muted, #6b7280)}
        .writer-post__status{display:inline-flex;width:max-content;border-radius:999px;background:color-mix(in srgb, var(--art-color-accent, #f8e2e2) 12%, #fff);padding:5px 9px;color:color-mix(in srgb, var(--art-color-accent, #8b0000) 78%, #000);font-size:11px;font-weight:800}
        .writer-post__edit{color:var(--art-color-accent, #a90000);font-weight:800;text-decoration:none}
        .writer-post__actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:flex-end}
        .writer-post__danger{border:0;background:none;color:#b91c1c;cursor:pointer;font-size:13px;font-weight:800;text-decoration:underline;padding:0}
        .writer-post__empty{padding:36px;text-align:center;color:var(--art-color-muted, #6b7280)}
        .writer-dashboard nav{margin-top:16px}
        @media(max-width:820px){.writer-dashboard__head{flex-direction:column}.writer-post{grid-template-columns:1fr}.writer-post__date{display:none}}
    </style>
    @php
        $portalUser = auth()->user();
        $portalSections = $portalUser ? app(\App\Platform\Core\Accounts\AccountSectionRegistry::class)->sections($portalUser) : [];
        $portalInitial = $portalUser ? mb_substr((string) $portalUser->name, 0, 1, 'UTF-8') : '?';
        $trashed = $trashed ?? false;
    @endphp
    <section class="ainpa-account-page" dir="auto">
        <div class="ainpa-account-shell">
            <div class="ainpa-account-workspace">
                <aside class="ainpa-account-sidebar">
                    @if($portalUser)
                        <div class="ainpa-account-profile-card">
                            <div class="ainpa-account-avatar" aria-hidden="true">{{ $portalInitial }}</div>
                            <div class="ainpa-account-profile-card__content">
                                <p>{{ __('blog::ui.signed-in-as') }}</p>
                                <h2>{{ $portalUser->name }}</h2>
                                <span>{{ $portalUser->email }}</span>
                            </div>
                        </div>
                        <nav class="ainpa-account-tabs" aria-label="{{ __('blog::ui.account-sections') }}">
                            @foreach($portalSections as $section)
                                <a href="{{ url('/account') }}#{{ $section['key'] }}" @class(['is-active' => $section['key'] === 'write-a-post'])>
                                    <span class="ainpa-account-tab-icon" aria-hidden="true">{{ $section['icon'] }}</span>
                                    <span>{{ $section['label'] }}</span>
                                    @if($section['badge'] !== null)<b>{{ $section['badge'] }}</b>@endif
                                </a>
                                @if($section['key'] === 'write-a-post')
                                    <div class="ainpa-account-subnav" aria-label="{{ __('blog::ui.post-sections') }}">
                                        <a href="{{ route('blog.writer.index', [], false) }}" @class(['is-active' => ! $trashed])>{{ __('blog::ui.all-posts') }}</a>
                                        <a href="{{ route('blog.writer.posts.create', [], false) }}">{{ __('blog::ui.add-new-post') }}</a>
                                        <a href="{{ route('blog.writer.posts.trash', [], false) }}" @class(['is-active' => $trashed])>{{ __('blog::ui.deleted-posts') }}</a>
                                    </div>
                                @endif
                            @endforeach
                        </nav>
                        <div class="ainpa-account-sidebar__actions">
                            <form method="POST" action="{{ route('logout') }}" class="ainpa-account-logout">
                                @csrf
                                <button type="submit" class="ainpa-account-button ainpa-account-button--ghost">{{ __('blog::ui.log-out') }}</button>
                            </form>
                        </div>
                    @endif
                </aside>
                <main class="ainpa-account-tab-panels">
                    <div role="tabpanel" style="min-height:0">
                        <div class="writer-dashboard">
                            <header class="writer-dashboard__head">
                                <div>
                                    <h1>{{ $trashed ? __('blog::ui.deleted-posts') : __('blog::ui.my-blog-posts') }}</h1>
                                    <p>{{ $trashed ? __('blog::ui.deleted-posts-intro') : __('blog::ui.my-blog-posts-intro') }}</p>
                                </div>
                                <a class="writer-dashboard__button" href="{{ route('blog.writer.posts.create', [], false) }}">{{ __('blog::ui.add-new-post') }}</a>
                            </header>
                            @if(session('status'))<div class="writer-dashboard__notice">{{ session('status') }}</div>@endif
                            <section class="writer-posts">
                                @forelse($posts as $post)
                                    <article class="writer-post">
                                        <div><strong>{{ $post->title }}</strong><small>{{ $post->category?->name ?: __('blog::ui.admin_posts_uncategorized') }}@if($post->tags->isNotEmpty()) &middot; {{ $post->tags->pluck('name')->take(3)->implode(', ') }}@endif</small></div>
                                        @php($writerStatus = match ($post->status) { 'published' => __('blog::ui.admin_posts_published'), 'scheduled' => __('blog::ui.admin_posts_scheduled'), 'private' => __('blog::ui.status-private'), 'archived' => __('blog::ui.status-archived'), default => __('blog::ui.admin_posts_draft') })
                                        <span class="writer-post__status">{{ $post->review_status === 'pending' ? __('blog::ui.pending-review') : $writerStatus }}</span>
                                        <small class="writer-post__date">{{ $trashed ? __('blog::ui.deleted-at', ['date' => $post->deleted_at?->format('Y-m-d H:i')]) : __('blog::ui.updated-at', ['date' => $post->updated_at?->format('Y-m-d H:i')]) }}</small>
                                        <div class="writer-post__actions">
                                            @if($trashed)
                                                <form method="POST" action="{{ route('blog.writer.posts.restore', $post->id, false) }}" style="margin:0">
                                                    @csrf
                                                    <button type="submit" class="writer-dashboard__button" style="min-height:36px">{{ __('blog::ui.restore') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('blog.writer.posts.force-destroy', $post->id, false) }}" style="margin:0" onsubmit="return confirm('{{ __('blog::ui.confirm-delete-permanently') }}');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="writer-post__danger">{{ __('blog::ui.delete-permanently') }}</button>
                                                </form>
                                            @else
                                                <a class="writer-post__edit" href="{{ route('blog.writer.posts.edit', $post, false) }}">{{ __('blog::ui.open-editor') }}</a>
                                                <form method="POST" action="{{ route('blog.writer.posts.destroy', $post, false) }}" style="margin:0" onsubmit="return confirm('{{ __('blog::ui.account_confirm_delete_post') }}');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="writer-post__danger">{{ __('blog::ui.delete') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </article>
                                @empty
                                    <div class="writer-post__empty">{{ $trashed ? __('blog::ui.trash-empty') : __('blog::ui.no-writer-posts') }}</div>
                                @endforelse
                            </section>
                            <x-platform-pagination :paginator="$posts" />
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </section>
</x-frontend-layout>
