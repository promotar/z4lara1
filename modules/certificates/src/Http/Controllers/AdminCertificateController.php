<?php

namespace Modules\Certificates\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Certificates\Models\Certificate;
use Modules\Certificates\Rules\AvailableCertificateSlug;
use Throwable;

class AdminCertificateController extends Controller
{
    public function index(Request $request): View
    {
        [$query, $search, $view] = $this->certificatesQuery($request);

        return view('certificates::admin.index', [
            'certificates' => $query->orderByDesc('date')->orderByDesc('id')->paginate(25)->withQueryString(),
            'search' => $search,
            'view' => $view,
            'counts' => $this->counts(),
        ]);
    }

    public function create(): View
    {
        return view('certificates::admin.form', ['certificate' => new Certificate]);
    }

    public function edit(Certificate $certificate): View
    {
        return view('certificates::admin.form', compact('certificate'));
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new Certificate);
    }

    public function update(Request $request, Certificate $certificate): RedirectResponse
    {
        return $this->save($request, $certificate);
    }

    public function destroy(Request $request, Certificate $certificate): RedirectResponse
    {
        $certificate->delete();

        return $this->redirectToIndex($request)->with('status', __('certificates::message.moved_trash'));
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $this->validatedIds($request);
        $certificates = Certificate::query()->whereIn('id', $ids)->get();

        foreach ($certificates as $certificate) {
            $certificate->delete();
        }

        $count = $certificates->count();

        return $this->redirectToIndex($request)->with('status', $count === 1 ? __('certificates::message.moved_trash') : __('certificates::message.moved_trash_count', ['count' => $count]));
    }

    public function restore(Request $request, Certificate $certificate): RedirectResponse
    {
        $certificate->restore();

        return $this->redirectToIndex($request, true)->with('status', __('certificates::message.restored'));
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $ids = $this->validatedIds($request);
        $certificates = Certificate::onlyTrashed()->whereIn('id', $ids)->get();

        foreach ($certificates as $certificate) {
            $certificate->restore();
        }

        $count = $certificates->count();

        return $this->redirectToIndex($request, true)->with('status', $count === 1 ? __('certificates::message.restored') : __('certificates::message.restored_count', ['count' => $count]));
    }

    public function forceDestroy(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->purgeCertificate($certificate);

        return $this->redirectToIndex($request, true)->with('status', __('certificates::message.deleted'));
    }

    public function bulkForceDestroy(Request $request): RedirectResponse
    {
        $ids = $this->validatedIds($request);
        $certificates = Certificate::onlyTrashed()->whereIn('id', $ids)->get();

        foreach ($certificates as $certificate) {
            $this->purgeCertificate($certificate);
        }

        $count = $certificates->count();

        return $this->redirectToIndex($request, true)->with('status', $count === 1 ? __('certificates::message.deleted') : __('certificates::message.deleted_count', ['count' => $count]));
    }

    public function emptyTrash(Request $request): RedirectResponse
    {
        $certificates = Certificate::onlyTrashed()->get();

        foreach ($certificates as $certificate) {
            $this->purgeCertificate($certificate);
        }

        $count = $certificates->count();

        return $this->redirectToIndex($request, true)->with('status', $count === 0 ? __('certificates::message.trash_empty') : __('certificates::message.deleted_count_plural', ['count' => $count]));
    }

    protected function indexRouteName(): string
    {
        return 'admin.plugins.certificates.index';
    }

    protected function redirectToIndex(Request $request, bool $trash = false): RedirectResponse
    {
        return redirect()->route($this->indexRouteName(), $trash ? ['view' => 'trash'] : []);
    }

    /**
     * Shared administrative and issuer search. Supports an "all" (default) or
     * "trash" view, with literal SQL wildcard handling for partial lookup.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: string, 2: string}
     */
    protected function certificatesQuery(Request $request): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
        ]);
        $view = $request->string('view')->toString() === 'trash' ? 'trash' : 'all';
        $search = trim($filters['q'] ?? $filters['slug'] ?? '');
        $query = $view === 'trash' ? Certificate::onlyTrashed() : Certificate::query();
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(static function ($query) use ($pattern): void {
                $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("slug LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        return [$query, $search, $view];
    }

    /** @return array{all: int, trash: int} */
    protected function counts(): array
    {
        return [
            'all' => Certificate::query()->count(),
            'trash' => Certificate::onlyTrashed()->count(),
        ];
    }

    /** @return array<int, int> */
    private function validatedIds(Request $request): array
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        return array_map('intval', $validated['ids']);
    }

    private function purgeCertificate(Certificate $certificate): void
    {
        if (is_string($certificate->image) && str_starts_with($certificate->image, 'certificates/images/')) {
            Storage::disk('local')->delete($certificate->image);
        }

        $certificate->forceDelete();
    }

    protected function save(Request $request, Certificate $certificate, string $editRoute = 'admin.plugins.certificates.edit', ?string $successMessage = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'slug' => ['required', 'string', 'max:190', 'regex:/^[\pL\pN][\pL\pN._-]*$/uD', Rule::unique('certificates', 'slug')->ignore($certificate->getKey()), new AvailableCertificateSlug],
            'image' => [$certificate->exists ? 'nullable' : 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ], [
            'slug.unique' => 'This number or slug is already used by another certificate.',
            'slug.regex' => 'Enter the full slug using letters or numbers and hyphens only, without spaces or extra paths.',
        ]);

        $oldImage = $certificate->image;
        $newImage = null;
        try {
            if ($request->hasFile('image')) {
                $newImage = $request->file('image')->store('certificates/images', 'local');
                if (! is_string($newImage)) {
                    throw new \RuntimeException('The certificate image could not be saved.');
                }
            }
            $certificate->fill([
                'name' => $data['name'],
                'date' => $data['date'],
                'slug' => $data['slug'],
                'image' => $newImage ?? $oldImage,
            ])->save();
        } catch (Throwable $exception) {
            if ($newImage && ! ($certificate->exists && $certificate->fresh()?->image === $newImage)) {
                Storage::disk('local')->delete($newImage);
            }
            throw $exception;
        }

        if ($newImage && $oldImage && $newImage !== $oldImage && str_starts_with($oldImage, 'certificates/images/')) {
            Storage::disk('local')->delete($oldImage);
        }

        return redirect()->route($editRoute, $certificate)->with('status', $successMessage ?? __('certificates::message.certificate_saved'));
    }
}
