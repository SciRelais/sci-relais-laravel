<?php

declare(strict_types=1);

namespace SciRelais\Laravel\Events;

/**
 * Une course Sci Express a changé d'état : requested, assigned, at_pickup, picked_up, delivered, cancelled, expired, failed.
 * Peut arriver deux fois ou dans le désordre : comparez $occurredAt avant d'écraser un état.
 * $courier : name, phone, vehicle dès qu'un coursier a la course, sinon null.
 * $account : le compte de l'adresse de notification, ou null avec l'adresse unique du fichier .env.
 */
class ExpressStatusChanged
{
    /** @param  array{name?: string, phone?: string, vehicle?: string}|null  $courier */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly ?string $reference,
        public readonly string $status,
        public readonly ?array $courier,
        public readonly string $occurredAt,
        public readonly ?string $account = null,
    ) {}
}
