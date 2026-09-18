<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CaptchaService
{
    /**
     * Generate a new captcha challenge (Math or Alphanumeric) with rendered SVG.
     */
    public function generate(): array
    {
        $num1 = random_int(2, 18);
        $num2 = random_int(1, 9);
        $isAdd = random_int(0, 1) === 1;

        if ($isAdd) {
            $answer = $num1 + $num2;
            $question = "{$num1} + {$num2}";
        } else {
            // Subtraction: make sure result is positive
            $top = max($num1, $num2) + random_int(1, 5);
            $bottom = min($num1, $num2);
            $answer = $top - $bottom;
            $question = "{$top} - {$bottom}";
        }

        $key = 'cap_' . Str::random(24);

        // Store expected answer for 10 minutes
        Cache::put("captcha_{$key}", (string) $answer, now()->addMinutes(10));

        $svg = $this->generateSvg($question);

        return [
            'captcha_key' => $key,
            'question' => "{$question} = ?",
            'svg' => $svg,
            'expires_in_seconds' => 600,
        ];
    }

    /**
     * Validate the provided captcha key and answer.
     *
     * @throws ValidationException
     */
    public function validate(?string $key, ?string $answer): bool
    {
        if (empty($key) || ! is_string($key)) {
            throw ValidationException::withMessages([
                'captcha_answer' => ['Kode keamanan (Captcha) wajib diisi untuk verifikasi anti-bot.'],
            ]);
        }

        if ($answer === null || trim((string) $answer) === '') {
            throw ValidationException::withMessages([
                'captcha_answer' => ['Harap masukkan jawaban kode keamanan (Captcha).'],
            ]);
        }

        $cachedAnswer = Cache::get("captcha_{$key}");

        if (! $cachedAnswer) {
            throw ValidationException::withMessages([
                'captcha_answer' => ['Kode keamanan (Captcha) telah kadaluarsa. Silakan muat ulang captcha baru.'],
            ]);
        }

        // Single-use token: remove immediately
        Cache::forget("captcha_{$key}");

        if (trim(strtolower((string) $cachedAnswer)) !== trim(strtolower((string) $answer))) {
            throw ValidationException::withMessages([
                'captcha_answer' => ['Jawaban kode keamanan (Captcha) salah. Silakan coba lagi.'],
            ]);
        }

        return true;
    }

    /**
     * Generate visual SVG for the captcha question.
     */
    protected function generateSvg(string $question): string
    {
        $display = "{$question} = ?";
        return '<svg xmlns="http://www.w3.org/2000/svg" width="130" height="38" viewBox="0 0 130 38" fill="none">
            <rect width="130" height="38" rx="6" fill="#0f172a" stroke="#334155" stroke-width="1"/>
            <path d="M5 19 Q 30 5, 65 19 T 125 19" stroke="#38bdf8" stroke-width="1" stroke-opacity="0.3" fill="none"/>
            <path d="M5 28 Q 45 36, 85 22 T 125 12" stroke="#818cf8" stroke-width="1" stroke-opacity="0.25" fill="none"/>
            <circle cx="15" cy="10" r="1.5" fill="#38bdf8" fill-opacity="0.5"/>
            <circle cx="65" cy="30" r="1.5" fill="#818cf8" fill-opacity="0.5"/>
            <circle cx="115" cy="12" r="1.5" fill="#38bdf8" fill-opacity="0.5"/>
            <text x="65" y="25" font-family="monospace, sans-serif" font-size="18" font-weight="700" fill="#f8fafc" text-anchor="middle" letter-spacing="1">' . htmlspecialchars($display) . '</text>
        </svg>';
    }
}
