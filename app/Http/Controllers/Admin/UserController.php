<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Core\Services\SettingsRepository;
use App\Platform\Core\Users\UserDeletionCoordinator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('admin.users.index', [
            'users' => User::query()
                ->with('roles')
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    });
                })
                ->orderBy('id')
                ->paginate(50)
                ->withQueryString(),
            'roles' => Role::orderBy('name')->get(),
            'search' => $search,
        ]);
    }

    public function store(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-.\s]{5,50}$/'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $roles = $data['roles'] ?? [];

        if ($roles === []) {
            $defaultRole = $settings->values()['general.default_user_role'] ?? 'user';
            $roles = Role::where('name', $defaultRole)->exists() ? [$defaultRole] : [];
        }

        $this->assertSuperAdminAssignmentAllowed($request, null, $roles);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($roles);

        return back()->with('status', __('core.users.flash.created'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-.\s]{5,50}$/'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $roles = $data['roles'] ?? [];
        $this->assertSuperAdminAssignmentAllowed($request, $user, $roles);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = filled($data['phone'] ?? null) ? trim($data['phone']) : null;
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->syncRoles($roles);

        return back()->with('status', __('core.users.flash.updated'));
    }

    public function verifyEmail(User $user): RedirectResponse
    {
        if ($user->email_verified_at !== null) {
            return back()->with('status', __('core.users.flash.email_verified'));
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        return back()->with('status', __('core.users.flash.email_verified_by_admin'));
    }

    public function destroy(Request $request, User $user, UserDeletionCoordinator $deletions): RedirectResponse
    {
        if ($request->user()?->is($user)) {
            throw ValidationException::withMessages([
                'user' => __('core.users.validation.cannot_delete_self'),
            ]);
        }

        if ($user->hasRole('super-admin') && ! User::role('super-admin')->whereKeyNot($user->getKey())->exists()) {
            throw ValidationException::withMessages([
                'user' => __('core.users.validation.last_super_admin'),
            ]);
        }

        $blockers = $deletions->blockers($user);
        if ($blockers !== []) {
            throw ValidationException::withMessages(['user' => $blockers]);
        }

        DB::transaction(function () use ($deletions, $user): void {
            $deletions->cleanup($user);
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        }, 3);

        return redirect()->route('admin.users.index')->with('status', __('core.users.flash.deleted'));
    }

    /** @param list<string> $roles */
    private function assertSuperAdminAssignmentAllowed(Request $request, ?User $target, array $roles): void
    {
        $willBeSuperAdmin = in_array('super-admin', $roles, true);
        $isSuperAdmin = $target?->hasRole('super-admin') ?? false;

        if ($willBeSuperAdmin === $isSuperAdmin) {
            return;
        }

        abort_unless($request->user()?->hasRole('super-admin'), 403);

        if (
            $isSuperAdmin
            && ! $willBeSuperAdmin
            && ! User::role('super-admin')->whereKeyNot($target->getKey())->exists()
        ) {
            abort(422, __('core.users.validation.last_super_admin_assignment'));
        }
    }
}
