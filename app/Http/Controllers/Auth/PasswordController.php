<?php

namespace App\Http\Controllers\Auth;

use App\Data\ChangeOwnPasswordData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeOwnPasswordRequest;
use App\Services\AdminUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function __construct(private readonly AdminUserService $users) {}

    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(ChangeOwnPasswordRequest $request): RedirectResponse
    {
        $this->users->changeOwnPassword(
            $request->user(),
            ChangeOwnPasswordData::fromArray($request->validated()),
            $request->session()->getId(),
        );

        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Password updated. Other sessions and API tokens were revoked.');
    }
}
