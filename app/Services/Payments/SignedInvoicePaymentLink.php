<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class SignedInvoicePaymentLink
{
    public function url(Invoice $invoice): string
    {
        return URL::signedRoute('payments.signed.show', [
            'token' => $this->encode((string) $invoice->getKey()),
        ]);
    }

    public function resolve(string $token): Invoice
    {
        try {
            $id = Crypt::decryptString($this->decode($token));
        } catch (Throwable) {
            throw new NotFoundHttpException;
        }

        if (! ctype_digit($id) || (int) $id < 1) {
            throw new NotFoundHttpException;
        }

        return Invoice::query()->findOrFail((int) $id);
    }

    private function encode(string $value): string
    {
        return rtrim(
            strtr(base64_encode(Crypt::encryptString($value)), '+/', '-_'),
            '=',
        );
    }

    private function decode(string $token): string
    {
        if ($token === '' || preg_match('/\A[A-Za-z0-9_-]+\z/D', $token) !== 1) {
            throw new NotFoundHttpException;
        }

        $base64 = strtr($token, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            throw new NotFoundHttpException;
        }

        return $decoded;
    }
}
