<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneIsVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasVerifiedPhone()) {
            return response()->json([
                'message' => 'Your phone number is not verified. Please verify your phone number to continue.',
                'error' => 'PHONE_NOT_VERIFIED',
                'phone' => $user?->phone,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
