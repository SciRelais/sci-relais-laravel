<?php

declare(strict_types=1);

namespace SciRelais\Laravel\Events;

/**
 * Un envoi a changé d'état. Peut arriver deux fois ou dans le désordre : comparez $occurredAt avant d'écraser un état.
 * $account : le compte de l'adresse de notification, ou null avec l'adresse unique du fichier .env.
 */
class ShipmentStatusChanged
{
    public function __construct(public readonly string $id, public readonly string $trackingCode, public readonly ?string $reference, public readonly string $status, public readonly string $occurredAt, public readonly ?string $account = null) {}
}
