<?php

declare(strict_types=1);

namespace SciRelais\Laravel;

use Closure;

/**
 * Plusieurs comptes Sci Relais dans une même application : chaque compte reçoit ses notifications sur
 * sa propre adresse (…/sci/webhook/{compte}), signées avec son propre secret.
 */
class WebhookSecrets
{
    /** @var (Closure(string): ?string)|null */
    private ?Closure $resolver = null;

    /** @param Closure(string): ?string $resolver reçoit le compte de l'adresse, renvoie son secret ou null s'il est inconnu */
    public function using(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    /** Sans compte dans l'adresse : le secret du fichier .env. Compte inconnu ou non géré : aucun secret, la notification est refusée. */
    public function for(?string $account): ?string
    {
        if ($account === null) {
            return filled(config('sci.webhook_secret')) ? (string) config('sci.webhook_secret') : null;
        }

        $secret = $this->resolver === null ? null : ($this->resolver)($account);

        return filled($secret) ? (string) $secret : null;
    }

    public function handlesAccounts(): bool
    {
        return $this->resolver !== null;
    }
}
