<?php

namespace App\Http\Controllers;

use App\Data\CreateAdminUserData;
use App\Data\ResetAdminUserPasswordData;
use App\Data\UpdateAdminUserData;
use App\Enums\UserRole;
use App\Exceptions\AdminLifecycleConflict;
use App\Http\Requests\Admin\IndexAdminUserRequest;
use App\Http\Requests\Admin\ResetAdminUserPasswordRequest;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(private readonly AdminUserService $users) {}

    public function index(IndexAdminUserRequest $request): View
    {
        return view('admin.users.index', [
            'users' => $this->users->paginate(
                $request->string('search')->trim()->toString(),
                $request->string('role')->toString(),
            ),
            'roles' => UserRole::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['roles' => UserRole::cases()]);
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $user = $this->users->create(CreateAdminUserData::fromArray($request->validated()), $request->user());

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', $this->users->editPage($user));
    }

    public function update(UpdateAdminUserRequest $request, User $user): RedirectResponse
    {
        try {
            $this->users->update($user, UpdateAdminUserData::fromArray($request->validated()), $request->user());
        } catch (AdminLifecycleConflict) {
            throw ValidationException::withMessages(['role' => 'At least one active Administrator must be retained.']);
        }

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'User details and global role updated.');
    }

    public function resetPassword(ResetAdminUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->users->resetPassword($user, ResetAdminUserPasswordData::fromArray($request->validated()), $request->user());

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'Password reset and all existing sessions and API tokens revoked.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        try {
            $this->users->suspend($user, $request->user());
        } catch (AdminLifecycleConflict) {
            throw ValidationException::withMessages(['status' => 'The last active Administrator cannot be suspended.']);
        }

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'Account suspended. Sessions, API tokens, and open assignments were revoked.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->users->reactivate($user, $request->user());

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'Account reactivated.');
    }
}
