<?php

declare(strict_types=1);

namespace ChariRelais\Laravel\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Refuse toute notification non signée, mal signée ou trop ancienne. */
class VerifyChariSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('chari.webhook_secret');
        abort_if($secret === '', 500, 'CHARI_WEBHOOK_SECRET manquant.');

        $parts = [];
        foreach (explode(',', (string) $request->header('X-Chari-Signature')) as $part) {
            [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$name] = $value;
        }
        $time = (string) ($parts['t'] ?? '');
        $signature = (string) ($parts['v1'] ?? '');

        // Calculée sur le corps brut : un JSON relu puis réécrit ne donnerait pas la même signature.
        $valid = ctype_digit($time)
            && hash_equals(hash_hmac('sha256', $time.'.'.$request->getContent(), $secret), $signature)
            && abs(time() - (int) $time) <= (int) config('chari.webhook_tolerance', 300);

        abort_unless($valid, 401);

        return $next($request);
    }
}
