<?php

namespace App\Http\Controllers\Auth;

use App\Data\AuthenticateSessionData;
use App\Exceptions\InvalidCredentials;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuthenticationService $authentication) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $this->authentication->authenticateSession(
                AuthenticateSessionData::fromArray($request->validated()),
                $request->session(),
            );
        } catch (InvalidCredentials) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authentication->logoutSession($request->session());

        return redirect()->route('login');
    }
}
