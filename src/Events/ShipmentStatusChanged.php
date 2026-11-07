<?php

declare(strict_types=1);

namespace ChariRelais\Laravel\Events;

/** Un envoi a changé d'état. Peut arriver deux fois ou dans le désordre : comparez $occurredAt avant d'écraser un état. */
class ShipmentStatusChanged
{
    public function __construct(public readonly string $id, public readonly string $trackingCode, public readonly ?string $reference, public readonly string $status, public readonly string $occurredAt) {}
}
