<?php

declare(strict_types=1);

namespace SciRelais\Laravel\Http;

use Closure;
use Illuminate\Http\Request;
use SciRelais\Laravel\WebhookSecrets;
use Symfony\Component\HttpFoundation\Response;

/** Refuse toute notification non signée, mal signée ou trop ancienne. */
class VerifySciSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->route('account');
        $secret = app(WebhookSecrets::class)->for(is_string($account) ? $account : null);
        // Sans compte : l'installation est incomplète. Avec un compte inconnu : quelqu'un essaie une adresse au hasard.
        abort_if($secret === null && $account === null, 500, 'SCI_WEBHOOK_SECRET manquant.');
        abort_if($secret === null, 401);

        $parts = [];
        foreach (explode(',', (string) $request->header('X-Sci-Signature')) as $part) {
            [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$name] = $value;
        }
        $time = (string) ($parts['t'] ?? '');
        $signature = (string) ($parts['v1'] ?? '');

        // Calculée sur le corps brut : un JSON relu puis réécrit ne donnerait pas la même signature.
        $valid = ctype_digit($time)
            && hash_equals(hash_hmac('sha256', $time.'.'.$request->getContent(), $secret), $signature)
            && abs(time() - (int) $time) <= (int) config('sci.webhook_tolerance', 300);

        abort_unless($valid, 401);

        return $next($request);
    }
}
