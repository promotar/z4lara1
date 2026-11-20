<?php

namespace Modules\Certificates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Certificates\Models\CertificateIssuer;

class AdminIssuerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $issuers = CertificateIssuer::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('user', function ($users) use ($search): void {
                    $term = '%'.$search.'%';
                    $users->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('certificates::admin.issuers.index', compact('issuers', 'search'));
    }

    public function users(Request $request): JsonResponse
    {
        $search = trim($request->string('q')->toString());
        if (mb_strlen($search) < 2) {
            return response()->json(['items' => []]);
        }

        $term = '%'.$search.'%';
        $users = User::query()
            ->where(function ($query) use ($term): void {
                $query->where('name', 'like', $term)->orWhere('email', 'like', $term);
            })
            ->whereNotIn('id', CertificateIssuer::query()->select('user_id'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json(['items' => $users]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', Rule::unique('certificate_issuers', 'user_id')],
        ]);

        CertificateIssuer::query()->create([
            'user_id' => $validated['user_id'],
            'status' => 'active',
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()->route('admin.plugins.certificates.issuers.index')->with('status', __('certificates::message.issuer_granted'));
    }

    public function update(Request $request, CertificateIssuer $issuer): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,suspended'],
        ]);

        $issuer->update([
            'status' => $validated['status'],
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()->route('admin.plugins.certificates.issuers.index')->with('status', __('certificates::message.issuer_updated'));
    }

    public function destroy(CertificateIssuer $issuer): RedirectResponse
    {
        $issuer->delete();

        return redirect()->route('admin.plugins.certificates.issuers.index')->with('status', __('certificates::message.issuer_removed'));
    }
}
