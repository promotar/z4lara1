<x-app-layout>
    <div class="lms-course-shell lms-people-page lms-instructor-requests-page">
        <div class="lms-course-container">
            <header class="lms-people-header lms-request-page-header">
                <div>
                    <a class="lms-back-link" href="{{ route('admin.plugins.lms.instructors') }}">{{ __('lms::ui.auto_e11a80fd37') }}</a>
                    <h1>{{ __('lms::ui.auto_e82cbbb3ca') }}</h1>
                    <p>{{ __('lms::ui.auto_01580b12e6') }}</p>
                </div>
                <a class="lms-btn lms-btn-primary" href="{{ route('admin.plugins.lms.instructors.create') }}">{{ __('lms::ui.auto_592c2ee889') }}</a>
            </header>

            @if (session('status'))
                <div class="lms-alert lms-alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="lms-alert lms-alert-error">{{ $errors->first() }}</div>
            @endif

            <nav class="lms-request-tabs" aria-label="{{ __('lms::ui.auto_70c92707d9') }}">
                @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
                    <a
                        href="{{ route('admin.plugins.lms.instructor-requests.index', ['status' => $value]) }}"
                        @class(['is-active' => $statusFilter === $value])
                    >{{ $label }}</a>
                @endforeach
            </nav>

            <section class="lms-people-table-card lms-request-table-card">
                <div class="lms-table-scroll">
                    <table class="lms-people-table lms-request-table">
                        <colgroup>
                            <col class="lms-request-col-user">
                            <col class="lms-request-col-expertise">
                            <col class="lms-request-col-message">
                            <col class="lms-request-col-status">
                            <col class="lms-request-col-submitted">
                            <col class="lms-request-col-decision">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>{{ __('lms::ui.auto_8f9bfe9d13') }}</th>
                                <th>{{ __('lms::ui.auto_57b0eaafb2') }}</th>
                                <th>{{ __('lms::ui.auto_15c2d85f1f') }}</th>
                                <th>{{ __('lms::ui.auto_ec53a8c4f0') }}</th>
                                <th>{{ __('lms::ui.auto_5c835c2242') }}</th>
                                <th>{{ __('lms::ui.auto_5d30f54fc5') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $application)
                                @php
                                    $requestMessage = trim((string) $application->message);
                                    $requestPreview = \Illuminate\Support\Str::limit($requestMessage, 150);
                                    $hasLongRequest = \Illuminate\Support\Str::length($requestMessage) > 150;
                                @endphp
                                <tr>
                                    <td data-label="User">
                                        <div class="lms-person">
                                            <span class="lms-person-avatar">{{ strtoupper(\Illuminate\Support\Str::substr($application->user?->name ?: $application->user?->email ?: 'U', 0, 1)) }}</span>
                                            <span>
                                                <strong>{{ $application->user?->name ?: 'Unavailable user' }}</strong>
                                                <small>{{ $application->user?->email }}</small>
                                            </span>
                                        </div>
                                    </td>
                                    <td data-label="Expertise">
                                        <span class="lms-request-expertise">{{ $application->expertise }}</span>
                                    </td>
                                    <td data-label="Request">
                                        <div class="lms-request-copy">
                                            <p class="lms-request-message">{{ $requestPreview ?: 'No message provided.' }}</p>
                                            @if ($hasLongRequest)
                                                <details class="lms-request-copy-details">
                                                    <summary>{{ __('lms::ui.auto_23610c5f22') }}</summary>
                                                    <p>{{ $requestMessage }}</p>
                                                </details>
                                            @endif
                                            @if ($application->decision_note)
                                                <small class="lms-decision-note">{{ __('lms::ui.auto_c83f7f1261') }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-label="Status">
                                        <span class="lms-state lms-state-{{ $application->status === 'approved' ? 'active' : ($application->status === 'rejected' ? 'inactive' : 'pending') }}">
                                            <i></i>{{ ucfirst($application->status) }}
                                        </span>
                                    </td>
                                    <td data-label="Submitted">
                                        <time class="lms-request-date" datetime="{{ $application->requested_at?->toIso8601String() }}">
                                            <strong>{{ $application->requested_at?->translatedFormat('d M Y') ?: '—' }}</strong>
                                            @if ($application->requested_at)
                                                <small>{{ $application->requested_at->format('H:i') }}</small>
                                            @endif
                                        </time>
                                    </td>
                                    <td data-label="Decision">
                                        @if ($application->status === 'pending')
                                            <div class="lms-request-actions">
                                                <form class="lms-request-approve-form" method="POST" action="{{ route('admin.plugins.lms.instructor-requests.approve', $application) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label>
                                                        <span>{{ __('lms::ui.auto_42575dd6c4') }}</span>
                                                        <select class="lms-select" name="publishing_policy">
                                                            <option value="review">{{ __('lms::ui.auto_67f98034e4') }}</option>
                                                            <option value="direct">{{ __('lms::ui.auto_e251a90d85') }}</option>
                                                        </select>
                                                    </label>
                                                    <button class="lms-btn lms-btn-primary" type="submit">{{ __('lms::ui.auto_6f7351657f') }}</button>
                                                </form>

                                                <details class="lms-request-reject">
                                                    <summary class="lms-btn lms-btn-ghost">{{ __('lms::ui.auto_d98ac12774') }}</summary>
                                                    <form method="POST" action="{{ route('admin.plugins.lms.instructor-requests.reject', $application) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <label>
                                                            <span>{{ __('lms::ui.auto_43ab2d3482') }}</span>
                                                            <textarea name="decision_note" rows="3" maxlength="2000"></textarea>
                                                        </label>
                                                        <button class="lms-btn lms-btn-danger" type="submit">{{ __('lms::ui.auto_f85fb27dcb') }}</button>
                                                    </form>
                                                </details>
                                            </div>
                                        @else
                                            <div class="lms-request-reviewed">
                                                <strong>{{ $application->reviewer?->name ?: 'Reviewed' }}</strong>
                                                @if ($application->reviewed_at)
                                                    <small>{{ $application->reviewed_at->translatedFormat('d M Y') }}</small>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="lms-empty-state">
                                            <span class="lms-empty-icon">R</span>
                                            <strong>{{ __('lms::ui.auto_a10d8cd82e') }}</strong>
                                            <p>{{ __('lms::ui.auto_8e8d62360c') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-platform-pagination :paginator="$requests" />
            </section>
        </div>
    </div>
</x-app-layout>
