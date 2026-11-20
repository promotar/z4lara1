<?php

namespace Modules\Lms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\CourseEnrollment;
use Modules\Lms\Models\Instructor;
use Modules\Lms\Models\InstructorRequest;
use Modules\Lms\Models\Invitation;
use Modules\Lms\Models\LessonProgress;
use Modules\Lms\Models\LmsNotification;
use Modules\Lms\Models\Student;
use Modules\Lms\Services\LmsNotificationService;
use Modules\Lms\Services\LmsPublicationService;
use Modules\Lms\Support\ResolvesPlatformMedia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class LmsPeopleController extends Controller
{
    use ResolvesPlatformMedia;

    public function students(Request $request): View
    {
        $perPage = in_array((int) $request->integer('per_page', 10), [10, 25, 50], true)
            ? (int) $request->integer('per_page', 10)
            : 10;

        $query = Student::query()->with('user');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($courseId = $request->integer('course_id')) {
            $query->whereHas('enrollments', fn ($builder) => $builder->where('course_id', $courseId));
        }

        $students = $query->latest('registered_at')->latest()->paginate($perPage)->withQueryString();
        $this->hydrateStudentMetrics($students->getCollection());
        $this->attachInvitationPendingFlags($students->getCollection(), 'student');

        return view('lms::admin.students.index', [
            'students' => $students,
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
            'perPage' => $perPage,
        ]);
    }

    public function inviteStudent(): View
    {
        return view('lms::admin.students.invite', [
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
            'pendingInvitations' => Invitation::query()
                ->with('course')
                ->where('role', 'student')
                ->whereIn('status', ['pending', 'sent', 'failed'])
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    public function sendStudentInvitation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'course_id' => ['required', Rule::exists('lms_courses', 'id')->whereNull('deleted_at')],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $course = Course::query()->findOrFail($validated['course_id']);
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && CourseEnrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->exists()) {
            return back()->withInput()->withErrors(['email' => 'This student is already enrolled in the selected course.']);
        }

        $pending = Invitation::query()
            ->where('email', $email)
            ->where('role', 'student')
            ->where('course_id', $course->id)
            ->whereIn('status', ['pending', 'sent'])
            ->where('expires_at', '>', now())
            ->first();

        if ($pending) {
            return back()->withInput()->withErrors(['email' => 'A pending invitation already exists. Use Resend Invitation below.']);
        }

        $invitation = DB::transaction(function () use ($email, $course, $user): Invitation {
            $invitation = Invitation::query()->create([
                'email' => $email,
                'role' => 'student',
                'course_id' => $course->id,
                'token_hash' => hash('sha256', Str::random(64)),
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
                'invited_by' => Auth::id(),
            ]);

            $student = Student::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
            if (! $student) {
                $student = new Student([
                    'email' => $email,
                    'created_by' => Auth::id(),
                ]);
            } elseif ($student->trashed()) {
                $student->restore();
            }

            // An active learner may be invited to another course without losing
            // access to courses they have already accepted.
            $student->fill([
                'user_id' => $student->user_id ?: $user?->id,
                'name' => $student->name ?: $user?->name ?: Str::headline(Str::before($email, '@')),
                'status' => $student->status === 'active' ? 'active' : 'pending',
                'updated_by' => Auth::id(),
            ])->save();

            return $invitation;
        });

        return $this->deliverInvitation($invitation, 'Student invitation sent successfully.');
    }

    public function resendInvitation(Invitation $invitation): RedirectResponse
    {
        abort_unless(in_array($invitation->role, ['student', 'instructor'], true), 404);

        return $this->deliverInvitation($invitation, 'Invitation resent successfully.');
    }

    public function showStudent(Student $student): View
    {
        $student->load('user');
        $enrollments = $student->user_id
            ? CourseEnrollment::query()->with('course')->where('user_id', $student->user_id)->latest('enrolled_at')->get()
            : collect();

        return view('lms::admin.students.show', compact('student', 'enrollments'));
    }

    public function studentCourses(Student $student): View
    {
        $enrollments = $student->user_id
            ? CourseEnrollment::query()->with('course')->where('user_id', $student->user_id)->latest('enrolled_at')->get()
            : collect();

        foreach ($enrollments as $enrollment) {
            $enrollment->setAttribute('progress_percent', $this->progressFor($student->user_id, $enrollment->course_id));
        }

        return view('lms::admin.students.courses', compact('student', 'enrollments'));
    }

    public function updateStudentStatus(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        $student->forceFill(['status' => $validated['status'], 'updated_by' => Auth::id()])->save();

        return back()->with('status', 'Student status updated successfully.');
    }

    public function setStudentTemporaryPassword(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($student, $validated): void {
            $email = mb_strtolower($student->email);
            $user = $student->user ?: User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $user) {
                $user = User::query()->create([
                    'name' => $student->name ?: Str::headline(Str::before($email, '@')),
                    'email' => $email,
                    'password' => Hash::make($validated['password']),
                ]);
            } else {
                $user->forceFill([
                    'name' => $user->name ?: $student->name,
                    'password' => Hash::make($validated['password']),
                ])->save();
            }

            $this->ensureRole($user, 'student');
            $student->forceFill([
                'user_id' => $user->id,
                'name' => $student->name ?: $user->name,
                'status' => 'active',
                'registered_at' => $student->registered_at ?: now(),
                'updated_by' => Auth::id(),
            ])->save();

            Invitation::query()
                ->where('role', 'student')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereIn('status', ['pending', 'sent'])
                ->whereNotNull('course_id')
                ->pluck('course_id')
                ->unique()
                ->each(fn ($courseId) => CourseEnrollment::query()->firstOrCreate([
                    'course_id' => $courseId,
                    'user_id' => $user->id,
                ], ['source' => 'admin_temporary_password', 'enrolled_at' => now()]));

            $this->refreshStudentEnrollmentCache($student);
        });

        return back()->with('status', 'Temporary password set. The student can sign in now; the invitation is still awaiting acceptance.');
    }

    public function removeEnrollment(Student $student, CourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($student->user_id && (int) $enrollment->user_id === (int) $student->user_id, 404);

        DB::transaction(function () use ($student, $enrollment): void {
            LessonProgress::query()->where('user_id', $student->user_id)->where('course_id', $enrollment->course_id)->delete();
            $enrollment->delete();
            $this->refreshStudentEnrollmentCache($student);
        });

        return back()->with('status', 'Course enrollment removed. The student account was preserved.');
    }

    public function instructors(Request $request): View
    {
        $perPage = in_array((int) $request->integer('per_page', 10), [10, 25, 50], true)
            ? (int) $request->integer('per_page', 10)
            : 10;
        $query = Instructor::query()->with('user')->withCount('courses');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($builder) => $builder
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($courseId = $request->integer('course_id')) {
            $query->whereHas('courses', fn ($builder) => $builder->whereKey($courseId));
        }

        $instructors = $query->latest()->paginate($perPage)->withQueryString();
        foreach ($instructors as $instructor) {
            $courseIds = $instructor->courses()->pluck('id');
            $instructor->setAttribute('students_count', CourseEnrollment::query()->whereIn('course_id', $courseIds)->distinct()->count('user_id'));
        }
        $this->attachInvitationPendingFlags($instructors->getCollection(), 'instructor');

        return view('lms::admin.instructors.index', [
            'instructors' => $instructors,
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
            'perPage' => $perPage,
        ]);
    }

    public function createInstructor(): View
    {
        return view('lms::admin.instructors.form', [
            'instructor' => null,
            'courses' => Course::query()->orderBy('title')->get(['id', 'title', 'instructor_id']),
            'selectedCourses' => [],
            'selectedUser' => null,
        ]);
    }

    public function storeInstructor(Request $request): RedirectResponse
    {
        $userId = $request->integer('user_id');
        $instructor = Instructor::withTrashed()->where('user_id', $userId)->first();
        if ($instructor && ! $instructor->trashed()) {
            return back()->withInput()->withErrors(['user_id' => 'This user is already registered as an instructor.']);
        }
        if ($instructor?->trashed()) {
            $instructor->restore();
        }

        return $this->saveInstructor($request, $instructor ?: new Instructor);
    }

    public function editInstructor(Instructor $instructor): View
    {
        return view('lms::admin.instructors.form', [
            'instructor' => $instructor,
            'courses' => Course::query()->orderBy('title')->get(['id', 'title', 'instructor_id']),
            'selectedCourses' => $instructor->courses()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'selectedUser' => $instructor->user,
        ]);
    }

    public function instructorUsers(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $linkedUserIds = Instructor::query()->whereNotNull('user_id')->pluck('user_id');

        $trashedUserIds = Instructor::onlyTrashed()->whereNotNull('user_id')->pluck('user_id')->all();

        $users = User::query()
            ->whereNotIn('id', $linkedUserIds)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(fn ($builder) => $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'));
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'restorable' => in_array($user->getKey(), $trashedUserIds, true),
            ]);

        return response()->json(['data' => $users]);
    }

    public function instructorRequests(Request $request): View
    {
        $status = trim((string) $request->query('status', 'pending'));
        $status = in_array($status, ['all', 'pending', 'approved', 'rejected'], true) ? $status : 'pending';
        $query = InstructorRequest::query()->with(['user', 'reviewer'])->latest('requested_at')->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('lms::admin.instructors.requests', [
            'requests' => $query->paginate(20)->withQueryString(),
            'statusFilter' => $status,
        ]);
    }

    public function approveInstructorRequest(Request $request, InstructorRequest $instructorRequest): RedirectResponse
    {
        if ($instructorRequest->status === 'approved') {
            return back()->with('status', 'This instructor request is already approved.');
        }

        abort_unless($instructorRequest->status === 'pending', 409, 'Only pending instructor requests can be approved.');

        $validated = $request->validate([
            'publishing_policy' => ['nullable', Rule::in(['review', 'direct'])],
        ]);
        $canPublishDirectly = ($validated['publishing_policy'] ?? 'review') === 'direct';

        $recipient = DB::transaction(function () use ($instructorRequest, $canPublishDirectly): User {
            $application = InstructorRequest::query()->lockForUpdate()->findOrFail($instructorRequest->getKey());
            $user = User::query()->findOrFail($application->user_id);
            [$firstName, $lastName] = $this->splitUserName($user->name);

            $instructor = Instructor::withTrashed()->where('user_id', $user->id)->first()
                ?: Instructor::withTrashed()->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])->first()
                ?: new Instructor;
            if ($instructor->trashed()) {
                $instructor->restore();
            }
            $instructor->forceFill([
                'user_id' => $user->id,
                'first_name' => $instructor->first_name ?: $firstName,
                'last_name' => $instructor->last_name ?: $lastName,
                'email' => mb_strtolower($user->email),
                'status' => 'active',
                'can_publish_directly' => $canPublishDirectly,
                'created_by' => $instructor->created_by ?: Auth::id(),
                'updated_by' => Auth::id(),
            ])->save();

            $this->ensureRole($user, 'instructor');
            $application->forceFill([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'decision_note' => null,
            ])->save();

            return $user;
        });

        app(LmsNotificationService::class)->notifyInstructorDecision($recipient, 'approved');

        return back()->with('status', 'Instructor request approved. The existing user can now open the teaching dashboard.');
    }

    public function rejectInstructorRequest(Request $request, InstructorRequest $instructorRequest): RedirectResponse
    {
        abort_unless($instructorRequest->status === 'pending', 409, 'Only pending instructor requests can be rejected.');

        $validated = $request->validate(['decision_note' => ['nullable', 'string', 'max:2000']]);
        $recipient = DB::transaction(function () use ($instructorRequest, $validated): User {
            $application = InstructorRequest::query()->lockForUpdate()->findOrFail($instructorRequest->getKey());
            $application->forceFill([
                'status' => 'rejected',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'decision_note' => trim((string) ($validated['decision_note'] ?? '')) ?: null,
            ])->save();

            return User::query()->findOrFail($application->user_id);
        });

        app(LmsNotificationService::class)->notifyInstructorDecision($recipient, 'rejected', $validated['decision_note'] ?? null);

        return back()->with('status', 'Instructor request rejected.');
    }

    public function notifications(Request $request): View
    {
        $notifications = LmsNotification::query()
            ->forUser($request->user())
            ->with('actor')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        LmsNotification::query()
            ->forUser($request->user())
            ->whereNull('read_at')
            ->whereIn('id', $notifications->getCollection()->pluck('id'))
            ->update(['read_at' => now()]);

        $notifications->getCollection()->transform(function (LmsNotification $notification): LmsNotification {
            if (preg_match('/^(Course|Lesson|Diploma) created by (.+)$/', (string) $notification->title, $matches) === 1) {
                $kind = __('lms::ui.notification_kind_'.strtolower($matches[1]));
                $notification->title = __('lms::ui.notification_created_by', ['kind' => $kind, 'actor' => $matches[2]]);
            }

            if (preg_match('/^(.*?) \x{00B7} (Draft \x{2014} approval required|Published)$/u', (string) $notification->body, $matches) === 1) {
                $status = $matches[2] === 'Published'
                    ? __('lms::ui.notification_status_published')
                    : __('lms::ui.notification_status_review');
                $notification->body = $matches[1].' · '.$status;
            }

            return $notification;
        });

        return view('lms::admin.notifications.index', compact('notifications'));
    }

    public function updateInstructor(Request $request, Instructor $instructor): RedirectResponse
    {
        return $this->saveInstructor($request, $instructor);
    }

    public function showInstructor(Instructor $instructor): View
    {
        $instructor->load(['user', 'courses' => fn ($builder) => $builder->withCount(['lessons', 'enrollments'])]);
        $this->attachInvitationPendingFlags(collect([$instructor]), 'instructor');

        return view('lms::admin.instructors.show', compact('instructor'));
    }

    public function updateInstructorStatus(Request $request, Instructor $instructor): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);

        $instructor->forceFill(['status' => $validated['status'], 'updated_by' => Auth::id()])->save();

        if ($instructor->user) {
            if ($validated['status'] === 'active') {
                $this->ensureRole($instructor->user, 'instructor');
            } elseif ($instructor->user->hasRole('instructor')) {
                // A deactivated instructor must lose dashboard access immediately.
                $instructor->user->removeRole('instructor');
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        }

        return back()->with('status', 'Instructor status updated successfully.');
    }

    public function deleteInstructor(Instructor $instructor): RedirectResponse
    {
        DB::transaction(function () use ($instructor): void {
            Course::query()
                ->where('instructor_id', $instructor->id)
                ->update([
                    'instructor_id' => null,
                    'instructor_name' => null,
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ]);

            Invitation::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($instructor->email)])
                ->where('role', 'instructor')
                ->whereIn('status', ['pending', 'sent', 'failed'])
                ->update([
                    'status' => 'cancelled',
                    'last_error' => 'Cancelled because the instructor record was deleted by an administrator.',
                    'updated_at' => now(),
                ]);

            if ($instructor->user?->hasRole('instructor')) {
                $instructor->user->removeRole('instructor');
            }

            // Keep the soft-deleted row consistent (no active status without access).
            $instructor->forceFill(['status' => 'inactive', 'updated_by' => Auth::id()])->save();
            $instructor->delete();
        });

        return redirect()
            ->route('admin.plugins.lms.instructors')
            ->with('status', 'Instructor deleted. Assigned courses were unassigned and the platform user account was kept.');
    }

    private function saveInstructor(Request $request, Instructor $instructor): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::unique('lms_instructors', 'user_id')->ignore($instructor->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'publishing_policy' => ['nullable', Rule::in(['review', 'direct'])],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'avatar_media_url' => ['nullable', 'string', 'max:2048'],
            'avatar_media_name' => ['nullable', 'string', 'max:255'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', Rule::exists('lms_courses', 'id')->whereNull('deleted_at')],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        if ($instructor->exists && $instructor->user_id && (int) $instructor->user_id !== (int) $user->id) {
            return back()->withInput()->withErrors(['user_id' => 'The linked platform user cannot be changed after instructor creation.']);
        }

        $email = mb_strtolower(trim($user->email));
        [$firstName, $lastName] = $this->splitUserName($user->name);
        $resolvedStatus = $validated['status'];
        $hadPendingRequest = InstructorRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        DB::transaction(function () use ($request, $validated, $email, $user, $instructor, $resolvedStatus, $firstName, $lastName): void {
            $instructor->fill([
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $validated['phone'] ?? null,
                'status' => $resolvedStatus,
                'can_publish_directly' => ($validated['publishing_policy'] ?? 'review') === 'direct',
                'created_by' => $instructor->exists ? $instructor->created_by : Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            if ($request->filled('avatar_media_url') && ($mediaPath = $this->platformMediaPathFromUrl($request->input('avatar_media_url')))) {
                $instructor->avatar_path = $mediaPath;
                $instructor->avatar_name = trim((string) $request->input('avatar_media_name')) ?: basename($mediaPath);
                $instructor->avatar_meta = ['source' => 'media_library', 'url' => Storage::disk('public')->url($mediaPath)];
            } elseif ($request->hasFile('avatar')) {
                if ($instructor->avatar_path) {
                    Storage::disk('public')->delete($instructor->avatar_path);
                }
                $file = $request->file('avatar');
                $instructor->avatar_path = $file->store('lms/instructors', 'public');
                $instructor->avatar_name = $file->getClientOriginalName();
                $instructor->avatar_meta = ['mime' => $file->getMimeType(), 'size' => $file->getSize()];
            }
            $instructor->save();

            $courseIds = collect($validated['course_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
            $removedCourses = Course::query()
                ->where('instructor_id', $instructor->id)
                ->when($courseIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $courseIds))
                ->get();
            foreach ($removedCourses as $course) {
                $course->forceFill([
                    'instructor_id' => null,
                    'instructor_name' => null,
                    'updated_by' => Auth::id(),
                ])->save();
            }

            if ($courseIds->isNotEmpty()) {
                Course::query()->whereIn('id', $courseIds)->get()->each(function (Course $course) use ($instructor): void {
                    $course->forceFill([
                        'instructor_id' => $instructor->id,
                        'instructor_name' => $instructor->name,
                        'updated_by' => Auth::id(),
                    ]);
                    $instructorRelationshipChanged = $course->isDirty('instructor_id');
                    $course->save();

                    // CourseObserver handles changed owners. If the owner was
                    // already stored by an older release, reconcile its missing
                    // owner enrollment without requiring a data migration.
                    if (! $instructorRelationshipChanged) {
                        app(LmsPublicationService::class)->reconcileInstructorOwners($course);
                    }
                });
            }

            $this->ensureRole($user, 'instructor');

            InstructorRequest::query()->where('user_id', $user->id)->where('status', 'pending')->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'decision_note' => 'Approved by direct administrator assignment.',
                'updated_at' => now(),
            ]);
        });

        if ($hadPendingRequest) {
            app(LmsNotificationService::class)->notifyInstructorDecision($user, 'approved');
        }

        return redirect()->route('admin.plugins.lms.instructors')->with('status', 'Instructor saved successfully.');
    }

    private function deliverInvitation(Invitation $invitation, string $successMessage): RedirectResponse
    {
        $token = Str::random(64);
        $invitation->forceFill([
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'last_error' => null,
        ])->save();
        $url = route('lms.front.invitations.show', ['token' => $token]);
        $courseName = $invitation->course?->title;
        $subject = $invitation->role === 'student' ? 'Your '.\Modules\Lms\Support\PlatformBrand::name().' course invitation' : 'Your '.\Modules\Lms\Support\PlatformBrand::name().' instructor invitation';
        $message = 'You have been invited to '.\Modules\Lms\Support\PlatformBrand::name().($courseName ? " for {$courseName}" : '').".\n\nCreate your account using this secure link:\n{$url}\n\nThis link expires in 7 days.";

        try {
            Mail::raw($message, fn ($mail) => $mail->to($invitation->email)->subject($subject));
            // sent_at records successful hand-off to the configured mail transport.
            // The workflow status intentionally remains pending until acceptance.
            $invitation->forceFill(['status' => 'pending', 'sent_at' => now()])->save();
        } catch (\Throwable $exception) {
            report($exception);
            $invitation->forceFill(['status' => 'failed', 'last_error' => Str::limit($exception->getMessage(), 1000)])->save();

            return back()->withErrors(['email' => 'The record was saved, but the email could not be sent. You can retry from the invitations list.']);
        }

        return redirect()->route($invitation->role === 'student' ? 'admin.plugins.lms.students.create' : 'admin.plugins.lms.instructors')->with('status', $successMessage);
    }

    private function ensureRole(User $user, string $role): void
    {
        $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        if ($role === 'instructor') {
            $basePermissions = [
                'lms.instructor.portal',
                'lms.instructor.courses.manage',
                'lms.instructor.lessons.manage',
            ];
            $permissions = collect($basePermissions)->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));
            $roleModel->givePermissionTo($permissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
        if (! $user->hasRole($roleModel)) {
            $user->assignRole($roleModel);
        }
    }

    /** @return array{0:string,1:string} */
    private function splitUserName(?string $name): array
    {
        $parts = preg_split('/\s+/u', trim((string) $name), 2, PREG_SPLIT_NO_EMPTY) ?: [];

        return [$parts[0] ?? 'Instructor', $parts[1] ?? 'Member'];
    }

    private function syncStudent(User $user): Student
    {
        $student = Student::withTrashed()->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])->first() ?: new Student;
        if ($student->trashed()) {
            $student->restore();
        }
        $student->fill([
            'user_id' => $user->id,
            'name' => $user->name ?: $user->email,
            'email' => mb_strtolower($user->email),
            'status' => $student->status ?: 'active',
            'registered_at' => $student->registered_at ?: now(),
            'last_active_at' => now(),
        ])->save();
        $this->refreshStudentEnrollmentCache($student);

        return $student;
    }

    private function refreshStudentEnrollmentCache(Student $student): void
    {
        $ids = $student->user_id ? CourseEnrollment::query()->where('user_id', $student->user_id)->pluck('course_id')->map(fn ($id) => (int) $id)->all() : [];
        $student->forceFill(['enrolled_course_ids' => $ids, 'enrolled_courses_count' => count($ids)])->save();
    }

    private function hydrateStudentMetrics($students): void
    {
        $userIds = collect($students)->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($userIds->isEmpty()) {
            foreach ($students as $student) {
                $student->setAttribute('actual_courses_count', 0);
                $student->setAttribute('enrolled_course_titles', []);
                $student->setAttribute('progress_percent', 0);
            }

            return;
        }

        $enrollments = DB::table('lms_course_enrollments as enrollment')
            ->leftJoin('lms_courses as course', 'course.id', '=', 'enrollment.course_id')
            ->whereIn('enrollment.user_id', $userIds)
            ->get(['enrollment.user_id', 'enrollment.course_id', 'course.title'])
            ->groupBy('user_id');
        $courseIds = $enrollments->flatten()->pluck('course_id')->map(fn ($id) => (int) $id)->unique()->values();

        $lessonTotals = $courseIds->isEmpty()
            ? collect()
            : DB::table('lms_lessons')
                ->whereIn('course_id', $courseIds)
                ->where('status', 'published')
                ->whereNull('deleted_at')
                ->selectRaw('course_id, COUNT(*) as aggregate')
                ->groupBy('course_id')
                ->pluck('aggregate', 'course_id');
        $completed = $courseIds->isEmpty()
            ? collect()
            : DB::table('lms_lesson_progress as progress')
                ->join('lms_lessons as lesson', 'lesson.id', '=', 'progress.lesson_id')
                ->whereIn('progress.user_id', $userIds)
                ->whereIn('progress.course_id', $courseIds)
                ->where('progress.completed', true)
                ->where('lesson.status', 'published')
                ->whereNull('lesson.deleted_at')
                ->selectRaw('progress.user_id, progress.course_id, COUNT(*) as aggregate')
                ->groupBy('progress.user_id', 'progress.course_id')
                ->get()
                ->keyBy(fn ($row): string => $row->user_id.':'.$row->course_id);

        foreach ($students as $student) {
            $studentEnrollments = $enrollments->get((int) $student->user_id, collect());
            $percentages = $studentEnrollments->map(function ($enrollment) use ($completed, $lessonTotals): int {
                $total = (int) $lessonTotals->get($enrollment->course_id, 0);
                $done = (int) ($completed->get($enrollment->user_id.':'.$enrollment->course_id)->aggregate ?? 0);

                return $total === 0 ? 0 : min(100, (int) round(($done / $total) * 100));
            });
            $titles = $studentEnrollments->map(function ($enrollment) use ($student): string {
                if (trim((string) $enrollment->title) !== '') {
                    return (string) $enrollment->title;
                }

                Log::warning('LMS enrollment references an unavailable course.', [
                    'student_id' => $student->id,
                    'user_id' => $student->user_id,
                    'course_id' => $enrollment->course_id,
                ]);

                return 'Unavailable course';
            })->values()->all();

            $student->setAttribute('actual_courses_count', $studentEnrollments->count());
            $student->setAttribute('enrolled_course_titles', $titles);
            $student->setAttribute('progress_percent', $percentages->isEmpty() ? 0 : (int) round($percentages->average()));
        }
    }

    private function attachInvitationPendingFlags($records, string $role): void
    {
        $emails = collect($records)
            ->pluck('email')
            ->filter()
            ->map(fn (string $email): string => mb_strtolower($email))
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return;
        }

        $pendingEmails = Invitation::query()
            ->where('role', $role)
            ->whereIn('status', ['pending', 'sent'])
            ->whereIn(DB::raw('LOWER(email)'), $emails->all())
            ->pluck('email')
            ->map(fn (string $email): string => mb_strtolower($email))
            ->flip();

        foreach ($records as $record) {
            $hasPendingInvitation = $pendingEmails->has(mb_strtolower((string) $record->email));
            // An administrator may activate the account before the invitee
            // completes the invitation. Some invitees already have a user
            // record (for example, as a student), so user_id alone must not
            // be treated as proof that the instructor invitation was accepted.
            $awaitingInstructorAcceptance = $role === 'instructor'
                && $record->status === 'active'
                && ! $hasPendingInvitation
                && ! $record->last_active_at;

            $record->setAttribute('invitation_pending', $hasPendingInvitation || $awaitingInstructorAcceptance);
        }
    }

    private function progressFor(?int $userId, int $courseId): int
    {
        if (! $userId) {
            return 0;
        }
        $lessonIds = Course::query()->find($courseId)?->lessons()->where('status', 'published')->pluck('id') ?: collect();
        if ($lessonIds->isEmpty()) {
            return 0;
        }
        $completed = LessonProgress::query()->where('user_id', $userId)->where('course_id', $courseId)->where('completed', true)->whereIn('lesson_id', $lessonIds)->count();

        return min(100, (int) round(($completed / $lessonIds->count()) * 100));
    }
}
