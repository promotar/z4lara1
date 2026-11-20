<?php

namespace Modules\Seo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Services\SeoRedirectResolver;
use Modules\Seo\Services\SeoSiteResolver;
use Throwable;

final class RedirectsController extends Controller
{
    public function index(Request $request): View
    {
        $redirects = null;
        $search = trim((string) $request->query('search', ''));

        try {
            $redirects = SeoRedirect::query()
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('from_path', 'like', '%'.$search.'%')
                            ->orWhere('to_path', 'like', '%'.$search.'%');
                    });
                })
                ->orderByDesc('priority')
                ->orderBy('from_path')
                ->paginate(25)
                ->withQueryString();
        } catch (Throwable $exception) {
            report($exception);
        }

        return view('seo::admin.redirects', compact('redirects', 'search'));
    }

    public function create(): View
    {
        $redirect = new SeoRedirect([
            'status_code' => 301,
            'is_active' => true,
            'priority' => 0,
            'query_behavior' => 'preserve',
        ]);

        return view('seo::admin.redirects-form', compact('redirect'));
    }

    public function store(Request $request, SeoSiteResolver $sites): RedirectResponse
    {
        $siteId = $sites->id();
        $data = $this->validated($request, $siteId);

        SeoRedirect::query()->create(['site_id' => $siteId, ...$data]);
        SeoRedirectResolver::flush($siteId);

        return redirect()
            ->route('admin.plugins.seo.redirects.index')
            ->with('status', __('seo::ui.redirect_saved'));
    }

    public function edit(SeoRedirect $redirect): View
    {
        return view('seo::admin.redirects-form', compact('redirect'));
    }

    public function update(
        Request $request,
        SeoRedirect $redirect,
        SeoSiteResolver $sites,
    ): RedirectResponse {
        $siteId = $sites->id();
        $data = $this->validated($request, $siteId, (int) $redirect->id);

        $redirect->fill($data)->save();
        SeoRedirectResolver::flush($siteId);

        return redirect()
            ->route('admin.plugins.seo.redirects.index')
            ->with('status', __('seo::ui.redirect_saved'));
    }

    public function destroy(SeoRedirect $redirect, SeoSiteResolver $sites): RedirectResponse
    {
        $siteId = $sites->id();
        $redirect->delete();
        SeoRedirectResolver::flush($siteId);

        return redirect()
            ->route('admin.plugins.seo.redirects.index')
            ->with('status', __('seo::ui.redirect_deleted'));
    }

    public function toggle(SeoRedirect $redirect, SeoSiteResolver $sites): RedirectResponse
    {
        $redirect->forceFill(['is_active' => ! $redirect->is_active])->save();
        SeoRedirectResolver::flush($sites->id());

        return back()->with('status', __('seo::ui.redirect_updated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $siteId, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:191'],
            'to_path' => ['nullable', 'string', 'max:2048'],
            'status_code' => ['required', 'integer', 'in:301,302,307,308,410'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'query_behavior' => ['nullable', 'in:preserve,ignore'],
            'module_slug' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $resolver = app(SeoRedirectResolver::class);
        SeoRedirectResolver::flush($siteId);
        $from = trim((string) $data['from_path']);
        $to = trim((string) ($data['to_path'] ?? ''));
        $status = (int) $data['status_code'];

        if (! str_starts_with($from, '/') || preg_match('~[?#]~', $from) === 1) {
            throw ValidationException::withMessages([
                'from_path' => __('seo::ui.redirect_from_invalid'),
            ]);
        }

        $from = $resolver->normalizePath($from);

        if ($status !== 410) {
            if ($to === '') {
                throw ValidationException::withMessages([
                    'to_path' => __('seo::ui.redirect_to_required'),
                ]);
            }

            if ($resolver->isExternal($to)) {
                if (! $resolver->externalAllowed($to, $request->getHost())) {
                    throw ValidationException::withMessages([
                        'to_path' => __('seo::ui.redirect_external_blocked'),
                    ]);
                }
            } else {
                if (! str_starts_with($to, '/')) {
                    throw ValidationException::withMessages([
                        'to_path' => __('seo::ui.redirect_to_invalid'),
                    ]);
                }

                $to = $resolver->normalizePath($to);
            }
        } else {
            $to = '';
        }

        $duplicate = SeoRedirect::query()
            ->where('site_id', $siteId)
            ->where('from_path', $from)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'from_path' => __('seo::ui.redirect_duplicate'),
            ]);
        }

        if ($status !== 410 && ! $resolver->isExternal($to)) {
            $redirects = array_values(array_filter(
                $resolver->redirects($siteId),
                static fn (SeoRedirect $redirect): bool => $ignoreId === null || (int) $redirect->id !== $ignoreId,
            ));

            if ($resolver->wouldLoop($redirects, $from, $to)) {
                throw ValidationException::withMessages([
                    'to_path' => __('seo::ui.redirect_loop'),
                ]);
            }
        }

        return [
            'from_path' => $from,
            'to_path' => $to,
            'status_code' => $status,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'module_slug' => ($data['module_slug'] ?? null) ?: null,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_external' => $resolver->isExternal($to),
            'query_behavior' => (string) ($data['query_behavior'] ?? 'preserve'),
            'notes' => ($data['notes'] ?? null) ?: null,
        ];
    }
}
