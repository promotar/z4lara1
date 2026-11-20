@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}">
@endpush
<x-app-layout>
    <main class="z4-exhibitions-admin" data-report-dashboard="{{ $section }}">
        <div class="z4-exhibitions-container">
            <header class="z4-exhibitions-heading">
                <div>
                    <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_72aa18c4d5') }}</span>
                    <h1>{{ $title }}</h1>
                    <p>{{ __('exhibitions::ui.auto_67f2c9b979') }}</p>
                </div>
                <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.reports.export', ['section' => $section, 'from' => request('from'), 'to' => request('to'), 'status' => $status, 'currency' => $currency]) }}">{{ __('exhibitions::ui.auto_6ee3ca7ebd') }}</a>
            </header>

            <nav class="z4-admin-tabs">
                @foreach ($sections as $key => $label)
                    <a @class(['is-active' => $section === $key]) href="{{ route('admin.plugins.exhibitions.reports.index', $key) }}">{{ $label }}</a>
                @endforeach
            </nav>

            <form class="z4-report-filters" method="get">
                <label>{{ __('exhibitions::ui.auto_5da618e8e4') }}<input type="date" name="from" value="{{ request('from', $from->setTimezone('Asia/Amman')->format('Y-m-d')) }}"></label>
                <label>{{ __('exhibitions::ui.to') }}<input type="date" name="to" value="{{ request('to', $to->setTimezone('Asia/Amman')->format('Y-m-d')) }}"></label>
                <label>{{ __('exhibitions::ui.auto_ec53a8c4f0') }}<input name="status" value="{{ $status }}" placeholder="{{ __('exhibitions::ui.auto_9ff346c27a') }}"></label>
                @if ($section === 'financial')
                    <label>{{ __('exhibitions::ui.auto_386c339d37') }}<input name="currency" maxlength="3" value="{{ $currency }}" placeholder="JOD"></label>
                @endif
                <button>{{ __('exhibitions::ui.auto_fe84b7c203') }}</button>
            </form>

            <section class="z4-report-metrics">
                @forelse ($metrics as $metric)
                    <article>
                        <small>{{ $metric['label'] }}</small>
                        <strong>{{ is_float($metric['value']) ? number_format($metric['value'], 2) : number_format($metric['value']) }}</strong>
                        <span>{{ __('exhibitions::ui.report_unit_'.$metric['unit']) }}</span>
                        <em>{{ $metric['source'] }}</em>
                    </article>
                @empty
                    <article><strong>{{ __('exhibitions::ui.auto_9588b6d4ec') }}</strong><span>{{ __('exhibitions::ui.auto_37c9ddac8d') }}</span></article>
                @endforelse
            </section>

            @php
                $max = max(1, (float) collect($rows)->max('value'));
            @endphp
            <section class="z4-exhibitions-panel">
                <div class="z4-exhibitions-panel-heading"><div><span>01</span><h2>{{ __('exhibitions::ui.auto_1ab6533a76') }}</h2></div></div>
                <div class="z4-report-bars">
                    @forelse ($rows as $row)
                        <article>
                            <div><strong>{{ str_replace('_', ' ', $row['label']) }}</strong><span>{{ number_format((float) $row['value'], 2) }} {{ __('exhibitions::ui.report_unit_'.$row['unit']) }}</span></div>
                            <div class="z4-report-bar"><i style="width:{{ min(100, max(2, ((float) $row['value'] / $max) * 100)) }}%"></i></div>
                            <small>{{ $row['source'] }}</small>
                        </article>
                    @empty
                        <div class="z4-exhibitions-empty">{{ __('exhibitions::ui.auto_ede789a275') }}</div>
                    @endforelse
                </div>
            </section>

            <section class="z4-exhibitions-panel">
                <h2>{{ __('exhibitions::ui.auto_18ac7d0c63') }}</h2>
                <dl class="z4-exhibitions-details">
                    @foreach ($definitions as $key => $value)
                        <div><dt>{{ __('exhibitions::ui.report_note_label_'.$key) }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
                <p><strong>{{ __('exhibitions::ui.auto_7dc2249508') }}</strong> {{ $timezone }}</p>
            </section>
        </div>
    </main>
</x-app-layout>
