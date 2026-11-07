<?php

declare(strict_types=1);

namespace ChariRelais\Laravel\Http;

use ChariRelais\Laravel\Events\ShipmentStatusChanged;
use ChariRelais\Laravel\Events\WebhookReceived;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Répond 2xx une fois les écouteurs passés. Si l'un d'eux échoue, Chari Relais réessaiera plus tard. */
class WebhookController
{
    public function __invoke(Request $request): Response
    {
        $id = (string) $request->json('id');
        $type = (string) $request->json('type');
        $occurredAt = (string) $request->json('occurred_at');
        $data = (array) $request->json('data', []);

        event(new WebhookReceived($id, $type, $occurredAt, $data));

        if ($type === 'shipment.status_changed') {
            event(new ShipmentStatusChanged($id, (string) ($data['tracking_code'] ?? ''), $data['reference'] ?? null, (string) ($data['status'] ?? ''), $occurredAt));
        }

        return response()->noContent();
    }
}
