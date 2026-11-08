<?php

declare(strict_types=1);

namespace SciRelais\Laravel\Events;

/** Toute notification signée, quel que soit son type. Une même notification (même $id) peut arriver deux fois. */
class WebhookReceived
{
    /** @param array<string, mixed> $data */
    public function __construct(public readonly string $id, public readonly string $type, public readonly string $occurredAt, public readonly array $data, public readonly ?string $account = null) {}
}
