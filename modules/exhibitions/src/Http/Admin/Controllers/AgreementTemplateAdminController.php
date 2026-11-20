<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use Modules\Exhibition\Application\Verification\ArtistVerificationService;

final class AgreementTemplateAdminController extends Controller
{
    public function __construct(
        private readonly ArtistVerificationService $verification,
        private readonly ExhibitionSettingsService $settings,
    ) {}

    public function index(): View
    {
        $templates = DB::table('exhibition_agreement_templates as templates')
            ->select('templates.*')
            ->selectSub(
                DB::table('exhibition_artist_agreements')->selectRaw('count(*)')->whereColumn('template_id', 'templates.id'),
                'assigned_count',
            )
            ->selectSub(
                DB::table('exhibition_artist_agreements')->selectRaw('count(*)')->whereColumn('template_id', 'templates.id')->whereNotNull('accepted_at'),
                'accepted_count',
            )
            ->orderByDesc('published_at')
            ->paginate(20, ['*'], 'agreements_page')
            ->withQueryString();

        return view('exhibitions::admin.agreements.index', [
            'templates' => $templates,
            'defaultTemplate' => DB::table('exhibition_agreement_templates')
                ->where('is_default', true)
                ->first(['version']),
            'defaultCommissionRate' => (string) $this->settings->value('exhibitions.commission.default_rate', '20.000'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'max:40', 'unique:exhibition_agreement_templates,version'],
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'min:100', 'max:50000'],
            'make_default' => ['nullable', 'boolean'],
        ]);
        $template = $this->verification->publishTemplate(
            $validated['version'],
            $validated['title'],
            $validated['content'],
            0,
            [],
            (int) $request->user()->id,
        );
        if (($validated['make_default'] ?? false) || ! DB::table('exhibition_agreement_templates')->where('is_default', true)->exists()) {
            $this->verification->setDefaultTemplate($template, (int) $request->user()->id);
        }

        return redirect()->route('admin.plugins.exhibitions.agreements.index')->with('status', 'Agreement version published successfully.');
    }

    public function makeDefault(Request $request, string $template): RedirectResponse
    {
        $row = $this->template($template);
        try {
            $this->verification->setDefaultTemplate($row, (int) $request->user()->id);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['template' => $exception->getMessage()]);
        }

        return back()->with('status', 'Default agreement updated. Every unsigned verification case now uses this version.');
    }

    public function setActive(Request $request, string $template): RedirectResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);
        $row = $this->template($template);
        try {
            $this->verification->setTemplateActive($row, (bool) $validated['active'], (int) $request->user()->id);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['template' => $exception->getMessage()]);
        }

        return back()->with('status', (bool) $validated['active'] ? 'Agreement version activated.' : 'Agreement version archived.');
    }

    private function template(string $publicId): object
    {
        return DB::table('exhibition_agreement_templates')->where('public_id', $publicId)->first() ?? abort(404);
    }
}
