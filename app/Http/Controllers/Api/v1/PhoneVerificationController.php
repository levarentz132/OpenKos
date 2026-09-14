<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendOtpRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Services\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneVerificationController extends Controller
{
    public function __construct(
        protected PhoneVerificationService $phoneVerificationService,
    ) {}

    /**
     * Send or resend OTP to user's phone.
     */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $phone = $request->input('phone', $user->phone);

        $this->phoneVerificationService->sendOtp($user, $phone);

        return response()->json([
            'message' => 'Verification code sent successfully.',
            'phone' => $phone,
        ]);
    }

    /**
     * Verify OTP code and activate phone status.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->phoneVerificationService->verifyOtp($user, $request->validated('code'));

        $freshUser = $user->fresh();

        return response()->json([
            'message' => 'Phone number verified successfully.',
            'phone_verified' => true,
            'phone_verified_at' => $freshUser->phone_verified_at?->toIso8601String(),
        ]);
    }
}
