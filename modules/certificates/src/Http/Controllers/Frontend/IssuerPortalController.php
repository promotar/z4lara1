<?php

namespace Modules\Certificates\Http\Controllers\Frontend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Certificates\Http\Controllers\AdminCertificateController;
use Modules\Certificates\Models\Certificate;
use Modules\Certificates\Services\IssuerAccessService;

class IssuerPortalController extends AdminCertificateController
{
    public function __construct(private readonly IssuerAccessService $access)
    {
    }

    protected function indexRouteName(): string
    {
        return 'certificates.manage.index';
    }

    public function index(Request $request): View
    {
        $this->access->activeIssuer($request->user());
        [$query, $search, $view] = $this->certificatesQuery($request);

        return view('certificates::frontend.index', [
            'certificates' => $query->orderByDesc('date')->orderByDesc('id')->paginate(25)->withQueryString(),
            'search' => $search,
            'view' => $view,
            'counts' => $this->counts(),
        ]);
    }

    public function create(): View
    {
        $this->access->activeIssuer(request()->user());

        return view('certificates::frontend.form', ['certificate' => new Certificate]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->access->activeIssuer($request->user());

        return $this->save($request, new Certificate, 'certificates.manage.edit', __('certificates::message.certificate_saved'));
    }

    public function edit(Certificate $certificate): View
    {
        $this->access->activeIssuer(request()->user());

        return view('certificates::frontend.form', compact('certificate'));
    }

    public function update(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->access->activeIssuer($request->user());

        return $this->save($request, $certificate, 'certificates.manage.edit', __('certificates::message.certificate_saved'));
    }

    public function destroy(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->access->activeIssuer($request->user());

        return parent::destroy($request, $certificate);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->access->activeIssuer($request->user());

        return parent::bulkDestroy($request);
    }

    public function restore(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->access->activeIssuer($request->user());

        return parent::restore($request, $certificate);
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $this->access->activeIssuer($request->user());

        return parent::bulkRestore($request);
    }

    public function forceDestroy(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->access->activeIssuer($request->user());

        return parent::forceDestroy($request, $certificate);
    }

    public function bulkForceDestroy(Request $request): RedirectResponse
    {
        $this->access->activeIssuer($request->user());

        return parent::bulkForceDestroy($request);
    }

    public function emptyTrash(Request $request): RedirectResponse
    {
        $this->access->activeIssuer($request->user());

        return parent::emptyTrash($request);
    }
}
