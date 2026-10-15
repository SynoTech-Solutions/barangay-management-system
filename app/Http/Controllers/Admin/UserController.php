<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Requests\Admin\UserRolesRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\User;
use App\Queries\UserListQuery;
use App\Services\Users\UserAdministration;
use App\Support\RouteNames;
use App\Tenancy\CurrentBarangay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class UserController extends Controller
{
    public function __construct(
        private readonly UserAdministration $users,
        private readonly CurrentBarangay $currentBarangay,
    ) {}

    public function index(Request $request, UserListQuery $query): Response
    {
        $barangay = $this->currentBarangay->require();

        return Inertia::render('admin/users/Index', [
            'users' => Paginated::from($query->paginate($barangay->id, $request->string('search')->toString(), $request->integer('per_page')), UserResource::class),
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/Form', [
            'user' => null,
            'roles' => RoleResource::collection($this->users->assignableRoles($this->currentBarangay->require()))->resolve(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->users->create(
            $this->currentBarangay->require(),
            [...$request->profile(), 'password' => $request->string('password')->toString()],
            $request->roleIds(),
            $this->actor($request),
        );

        Toast::success('messages.user_created');

        return to_route(RouteNames::ADMIN_USERS_INDEX);
    }

    public function edit(User $user): Response
    {
        $this->ensureInBarangay($user);
        $user->load('roleAssignments.role');

        return Inertia::render('admin/users/Form', [
            'user' => (new UserResource($user))->resolve(),
            'roles' => RoleResource::collection($this->users->assignableRoles($this->currentBarangay->require()))->resolve(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->ensureInBarangay($user);
        $this->users->update($user, $request->profile(), $this->actor($request));

        Toast::success('messages.user_updated');

        return to_route(RouteNames::ADMIN_USERS_EDIT, $user);
    }

    public function updateRoles(UserRolesRequest $request, User $user): RedirectResponse
    {
        $this->ensureInBarangay($user);
        $this->users->setRoles($user, $request->roleIds(), $this->actor($request));

        Toast::success('messages.user_roles_updated');

        return back();
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->ensureInBarangay($user);
        $this->users->setActive($user, false, $this->actor($request));

        Toast::success('messages.user_deactivated');

        return back();
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->ensureInBarangay($user);
        $this->users->setActive($user, true, $this->actor($request));

        Toast::success('messages.user_reactivated');

        return back();
    }

    public function resetPassword(ResetPasswordRequest $request, User $user): RedirectResponse
    {
        $this->ensureInBarangay($user);
        $this->users->resetPassword($user, $request->string('password')->toString(), $this->actor($request));

        Toast::success('messages.user_password_reset');

        return back();
    }

    private function ensureInBarangay(User $user): void
    {
        abort_unless($user->barangay_id === $this->currentBarangay->require()->id, HttpStatus::HTTP_NOT_FOUND);
    }
}
