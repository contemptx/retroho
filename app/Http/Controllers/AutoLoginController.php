<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AutoLoginToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutoLoginController extends Controller
{
    /**
     * Handle auto-login via one-time token.
     * 
     * @param string $token The one-time login token
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(string $token, Request $request): \Illuminate\Http\RedirectResponse
    {
        // Find the token
        $autoLoginToken = AutoLoginToken::where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$autoLoginToken) {
            return redirect()->route('login')
                ->withErrors(['token' => 'This login link is invalid or has expired. Please use your username and password to login.']);
        }

        // Get the user
        $user = $autoLoginToken->user;

        if (!$user) {
            return redirect()->route('login')
                ->withErrors(['token' => 'User not found. Please contact support.']);
        }

        // Check if user is active
        if (!$user->active) {
            return redirect()->route('login')
                ->withErrors(['token' => 'Your account is not active. Please contact support.']);
        }

        // Mark the token as used
        $autoLoginToken->markAsUsed($request->ip());

        // Log the user in
        Auth::login($user, remember: false);

        return redirect()->intended('/')
            ->withSuccess('You have been logged in successfully!');
    }
}
