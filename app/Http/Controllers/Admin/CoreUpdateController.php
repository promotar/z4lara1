<?php

namespace App\Http\Controllers\Admin;

use App\Platform\Core\Updates\CoreUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class CoreUpdateController extends Controller
{
    public function store(Request $request, CoreUpdateService $updates): RedirectResponse
    {
        $validated = $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:512000'],
            'confirm' => ['accepted'],
        ]);

        $result = $updates->apply($request->file('package'));

        return redirect()
            ->route('admin.settings.index', ['tab' => 'core-updates'])
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

}
