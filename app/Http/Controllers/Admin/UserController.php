<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::with(['roles', 'permissions'])->orderBy('id')->get(),
            'roles' => Role::orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,name'],
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
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($roles);
        $user->syncPermissions($data['permissions'] ?? []);

        return back()->with('status', 'تم إنشاء المستخدم.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $roles = $data['roles'] ?? [];
        $this->assertSuperAdminAssignmentAllowed($request, $user, $roles);

        $user->name = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->syncRoles($roles);
        $user->syncPermissions($data['permissions'] ?? []);

        return back()->with('status', 'تم تحديث المستخدم وصلاحياته.');
    }

    public function verifyEmail(User $user): RedirectResponse
    {
        if ($user->email_verified_at !== null) {
            return back()->with('status', 'البريد الإلكتروني لهذا المستخدم مفعّل بالفعل.');
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        return back()->with('status', 'تم تفعيل البريد الإلكتروني للمستخدم بواسطة الإدارة.');
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
            abort(422, 'The final super-admin assignment cannot be removed.');
        }
    }
}
