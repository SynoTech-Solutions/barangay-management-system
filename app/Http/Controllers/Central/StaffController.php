<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Queries\UserListQuery;
use App\Services\Users\UserAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(private readonly UserAdministration $users) {}

    public function index(Request $request, UserListQuery $query): Response
    {
        return Inertia::render('central/staff/Index', [
            'users' => Paginated::from($query->paginate(null, $request->string('search')->toString(), $request->integer('per_page')), UserResource::class),
            'roles' => RoleResource::collection($this->users->assignableRoles(null))->resolve(),
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->users->create(null, [...$request->profile(), 'password' => $request->string('password')->toString()], $request->roleIds(), $this->actor($request));
        Toast::success('messages.user_created');

        return back();
    }
}
