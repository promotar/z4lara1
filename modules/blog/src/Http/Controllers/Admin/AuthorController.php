<?php

namespace Modules\Blog\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Blog\Models\Author;

class AuthorController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $authors = Author::query()
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

        return view('blog::admin.authors.index', compact('authors', 'search'));
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
            ->whereNotIn('id', Author::query()->select('user_id'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json(['items' => $users]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', Rule::unique('blog_authors', 'user_id')],
            'publishing_policy' => ['required', 'in:review,direct'],
        ]);

        Author::query()->create([
            'user_id' => $validated['user_id'],
            'status' => 'active',
            'can_publish_directly' => $validated['publishing_policy'] === 'direct',
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('status', 'Author access added.');
    }

    public function update(Request $request, Author $author): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'publishing_policy' => ['required', 'in:review,direct'],
        ]);

        $author->update([
            'status' => $validated['status'],
            'can_publish_directly' => $validated['publishing_policy'] === 'direct',
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('status', 'Author access updated.');
    }

    public function destroy(Author $author): RedirectResponse
    {
        $author->delete();

        return back()->with('status', 'Author access removed. Existing posts were preserved.');
    }
}
