<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use App\Platform\Core\Localization\PlatformLocale;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Admin\AdminArtworkService;
use Modules\Exhibition\Application\Artworks\ArtworkTranslationService;
use Modules\Exhibition\Application\Media\ArtworkMediaService;

final class ArtworkAdminController extends Controller
{
    public function __construct(
        private readonly AdminArtworkService $artworks,
        private readonly ArtworkMediaService $media,
        private readonly ArtworkTranslationService $translations,
    ) {}

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);

        $locales = app(PlatformLocale::class)->languages();
        $settingsLocale = (string) app()->getLocale();
        $locale = array_key_exists($settingsLocale, $locales) ? $settingsLocale : PlatformLocale::DEFAULT;

        return view('exhibitions::admin.artworks.form', [
            'artwork' => null,
            'ownerUserId' => null,
            'artists' => $this->artworks->activeOwners(),
            'locale' => $locale,
            'locales' => $locales,
            'defaultLocale' => PlatformLocale::DEFAULT,
            'translation' => null,
            'baseArtwork' => null,
            'translatedLocales' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->persist($request);
    }

    public function edit(Request $request, string $artwork): View
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);
        $row = DB::table('exhibition_artworks as artwork')
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'artwork.artist_identity_id')
            ->where('artwork.public_id', $artwork)
            ->select('artwork.*', 'identity.core_user_id')
            ->first() ?? abort(404);
        abort_unless(in_array((string) $row->state, ['draft', 'submitted', 'changes_required', 'rejected'], true), 409);

        $locales = app(PlatformLocale::class)->languages();
        $settingsLocale = (string) app()->getLocale();
        $defaultLocale = array_key_exists($settingsLocale, $locales) ? $settingsLocale : PlatformLocale::DEFAULT;
        $locale = (string) $request->query('locale', $defaultLocale);
        if (! array_key_exists($locale, $locales)) {
            $locale = $defaultLocale;
        }

        $baseArtwork = $row;
        $translation = $this->translations->resolve((int) $row->id, $locale);
        $displayArtwork = $this->translations->apply(clone $row, $translation);

        return view('exhibitions::admin.artworks.form', [
            'artwork' => $displayArtwork,
            'ownerUserId' => (int) $row->core_user_id,
            'artists' => $this->artworks->activeOwners(),
            'locale' => $locale,
            'locales' => $locales,
            'defaultLocale' => PlatformLocale::DEFAULT,
            'translation' => $translation,
            'baseArtwork' => $baseArtwork,
            'translatedLocales' => $this->translations->translatedLocales((int) $row->id),
        ]);
    }

    public function update(Request $request, string $artwork): RedirectResponse
    {
        return $this->persist($request, $artwork);
    }

    private function persist(Request $request, ?string $publicId = null): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);
        $validated = $request->validate($this->rules());

        $locales = app(PlatformLocale::class)->languages();
        $settingsLocale = (string) app()->getLocale();
        $defaultLocale = array_key_exists($settingsLocale, $locales) ? $settingsLocale : PlatformLocale::DEFAULT;
        $locale = (string) $request->input('locale', $defaultLocale);
        if (! array_key_exists($locale, $locales)) {
            $locale = $defaultLocale;
        }
        $translationMode = $locale !== PlatformLocale::DEFAULT;

        if ($translationMode && $publicId !== null) {
            $existing = DB::table('exhibition_artworks')->where('public_id', $publicId)->first() ?? abort(404);
            $this->translations->save((int) $existing->id, $locale, $validated, (int) $request->user()->id);

            return redirect()
                ->route('admin.plugins.exhibitions.artworks.edit', ['artwork' => $publicId, 'locale' => $locale])
                ->with('status', 'Artwork translation saved.');
        }

        try {
            $saved = $this->artworks->save((int) $request->user()->id, $validated, $publicId);
            if ($translationMode && $publicId === null) {
                $this->translations->save((int) $saved->id, $locale, $validated, (int) $request->user()->id);
            }
            if ($request->hasFile('main_image')) {
                $this->media->uploadForAdministrator(
                    (int) $request->user()->id,
                    (string) $saved->public_id,
                    $request->file('main_image'),
                );
            }

        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['artwork' => $exception->getMessage()]);
        }

        if ($translationMode) {
            return redirect()
                ->route('admin.plugins.exhibitions.artworks.edit', ['artwork' => $saved->public_id, 'locale' => $locale])
                ->with('status', 'Artwork translation saved.');
        }

        return redirect()->route('admin.plugins.exhibitions.workspace', ['area' => 'artworks', 'tab' => 'drafts'])
            ->with('status', $publicId === null ? 'Artwork created as a private draft.' : 'Artwork draft updated.');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'artist_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:180'],
            'completion_year' => ['nullable', 'integer', 'between:1000,'.date('Y')],
            'work_type' => ['nullable', 'string', 'max:80'],
            'collection_name' => ['nullable', 'string', 'max:160'],
            'edition_type' => ['required', Rule::in(['original', 'limited'])],
            'edition_number' => ['nullable', 'required_if:edition_type,limited', 'integer', 'min:1', 'lte:edition_total'],
            'edition_total' => ['nullable', 'required_if:edition_type,limited', 'integer', 'between:1,100000'],
            'category' => ['nullable', 'string', 'max:100'],
            'artistic_style' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:160'],
            'technique' => ['nullable', 'string', 'max:160'],
            'material' => ['nullable', 'string', 'max:160'],
            'surface' => ['nullable', 'string', 'max:120'],
            'primary_colors' => ['nullable', 'string', 'max:500'],
            'orientation' => ['nullable', Rule::in(['portrait', 'landscape', 'square', 'panoramic', 'other'])],
            'width' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'height' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'depth' => ['nullable', 'numeric', 'gte:0', 'max:100000'],
            'dimension_unit' => ['required', Rule::in(['cm', 'in'])],
            'weight' => ['nullable', 'numeric', 'gte:0', 'max:100000'],
            'weight_unit' => ['required', Rule::in(['kg', 'lb'])],
            'is_framed' => ['nullable', 'boolean'],
            'frame_material' => ['nullable', 'required_if:is_framed,1', 'string', 'max:120'],
            'condition' => ['nullable', Rule::in(['new', 'excellent', 'good', 'fair', 'restored'])],
            'ready_to_hang' => ['nullable', 'boolean'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'detailed_description' => ['nullable', 'string', 'max:10000'],
            'artwork_story' => ['nullable', 'string', 'max:10000'],
            'inspiration_source' => ['nullable', 'string', 'max:5000'],
            'care_instructions' => ['nullable', 'string', 'max:3000'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'image_alt_text' => ['nullable', 'string', 'max:180'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'requested_price' => ['nullable', 'numeric', 'min:0.01', 'max:999999999.99'],
            'currency' => ['required', Rule::in(['JOD'])],
            'frame_cost' => ['nullable', 'numeric', 'gte:0', 'max:999999999.99'],
            'packaging_cost' => ['nullable', 'numeric', 'gte:0', 'max:999999999.99'],
            'availability' => ['nullable', Rule::in(['available', 'sold', 'unavailable', 'made_to_order', 'temporarily_unavailable'])], 'sold_buyer_name' => ['nullable', 'string', 'max:180'],
            'preparation_days' => ['nullable', 'integer', 'between:0,365'],
            'shipping_notes' => ['nullable', 'string', 'max:3000'],
            'main_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ];
    }
}
