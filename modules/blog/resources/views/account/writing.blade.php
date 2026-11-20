<section class="account-extension-section">
    <div class="account-section-heading">
        <div><h2>{{ __('blog::ui.auto_5dc52ca9ff') }}</h2><p>{{ __('blog::ui.auto_e97134f808') }}</p></div>
        <a class="account-section-action" href="{{ $createUrl }}">{{ __('blog::ui.auto_faf2a80615') }}</a>
    </div>

    <div class="account-item-list">
        @forelse($posts as $post)
            <div class="account-item">
                <a href="{{ route('blog.writer.posts.edit', $post, false) }}" style="display:flex;flex:1;min-width:0;gap:1rem;align-items:center;justify-content:space-between;color:inherit;text-decoration:none">
                    <span><strong>{{ $post->title }}</strong><small>{{ $post->review_status === 'pending' ? __('blog::ui.account_pending_review') : (in_array($post->status, ['draft','published','scheduled','private'], true) ? __('blog::ui.admin_posts_'.$post->status) : ucfirst($post->status)) }} &middot; {{ $post->updated_at?->format('Y-m-d H:i') }}</small></span>
                    <span>{{ __('blog::ui.auto_7dce122004') }}</span>
                </a>
                <form method="POST" action="{{ route('blog.writer.posts.destroy', $post, false) }}" style="margin:0;flex:0 0 auto" onsubmit="return confirm('{{ __('blog::ui.account_confirm_delete_post') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="border:1px solid var(--account-border);border-radius:.6rem;background:transparent;color:#b91c1c;cursor:pointer;font-size:.8rem;font-weight:800;padding:.45rem .7rem">{{ __('blog::ui.auto_f2a6c498fb') }}</button>
                </form>
            </div>
        @empty
            <a class="account-item" href="{{ $createUrl }}"><span><strong>{{ __('blog::ui.auto_57f0d952b2') }}</strong><small>{{ __('blog::ui.auto_0d39cd7303') }}</small></span><span>{{ __('blog::ui.auto_a6122a65ea') }}</span></a>
        @endforelse
    </div>

    <div class="account-section-heading">
        <p>{{ $canPublishDirectly ? __('blog::ui.account_publish_direct') : __('blog::ui.account_publish_review') }}</p>
        <a class="account-section-action" href="{{ $dashboardUrl }}">{{ __('blog::ui.auto_bfb211619b') }}</a>
    </div>

    @once
        <style>
            .account-subnav{display:grid;gap:.2rem;margin:.15rem 0 .45rem;padding-inline-start:.85rem;border-inline-start:1px solid rgb(255 255 255 / 20%)}
            .account-subnav a{display:block;padding:.34rem .5rem;border-radius:.5rem;color:#f0c9cc;font-size:.78rem;font-weight:700;text-decoration:none}
            .account-subnav a:hover{background:rgb(255 255 255 / 8%);color:#fff}
            .account-subnav[hidden]{display:none !important}
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var tab = document.getElementById('account-tab-write-a-post');
                if (!tab || document.getElementById('blog-post-subnav')) { return; }
                var links = [
                    [__('blog::ui.account_all_posts'), '{{ route('blog.writer.index', [], false) }}'],
                    [__('blog::ui.account_add_post'), '{{ route('blog.writer.posts.create', [], false) }}'],
                    [__('blog::ui.account_deleted_posts'), '{{ route('blog.writer.posts.trash', [], false) }}']
                ];
                var nav = document.createElement('nav');
                nav.id = 'blog-post-subnav';
                nav.className = 'account-subnav';
                nav.setAttribute('aria-label', @json(__('blog::ui.account_post_sections')));
                links.forEach(function (item) {
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
</section>
