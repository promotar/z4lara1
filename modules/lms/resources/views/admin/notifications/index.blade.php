<x-app-layout>
<div class="lms-course-shell lms-people-page"><div class="lms-course-container lms-narrow-container">
    <header class="lms-people-header"><div><span class="lms-eyebrow">{{ __('lms::ui.auto_deb8a4c45f') }}</span><h1>{{ __('lms::ui.auto_a274f4d467') }}</h1><p>{{ __('lms::ui.auto_a582d32720') }}</p></div></header>
    <section class="lms-notification-list">
        @forelse($notifications as $notification)
            @php($actionUrl = (string) data_get($notification->data, 'action_url', ''))
            <article @class(['is-unread'=>$notification->read_at===null])><span class="lms-notification-icon" aria-hidden="true">{{ strtoupper(\Illuminate\Support\Str::substr($notification->type,0,1)) }}</span><div><div><strong>{{ $notification->title }}</strong><time>{{ $notification->created_at?->diffForHumans() }}</time></div><p>{{ $notification->body }}</p>@if($notification->actor)<small>{{ __('lms::ui.notification_by') }} {{ $notification->actor->name }}</small>@endif</div>@if($actionUrl)<a class="lms-btn lms-btn-ghost" href="{{ $actionUrl }}">{{ __('lms::ui.auto_c3bf447eab') }}</a>@endif</article>
        @empty<div class="lms-empty-state"><span class="lms-empty-icon">N</span><strong>{{ __('lms::ui.auto_6b7245c98e') }}</strong><p>{{ __('lms::ui.auto_3affc1fbdd') }}</p></div>@endforelse
        <x-platform-pagination :paginator="$notifications" />
    </section>
</div></div>
</x-app-layout>
