# Chari Relais pour Laravel

Créez et suivez vos envois Chari Relais depuis votre application, et recevez leurs changements d'état. Laravel 11, 12 ou 13, PHP 8.2 ou plus.

## Installation

Le paquet n'est pas encore publié sur Packagist. Téléchargez le fichier zip depuis le guide d'intégration de Chari Relais, placez-le dans un dossier `packages/` à la racine de votre application, puis :

```bash
composer config repositories.chari artifact ./packages
composer require chari-relais/laravel
```

Dans `.env` :

```
CHARI_URL=https://votre-domaine-chari.example
CHARI_KEY=votre-cle
CHARI_WEBHOOK_SECRET=votre-secret
```

Vérifiez, sans rien créer ni débiter : `php artisan chari:check`

## Utilisation

```php
use ChariRelais\Laravel\Exceptions\ChariException;
use ChariRelais\Laravel\Facades\Chari;

$relays = Chari::relays(city: 'Moundou');

$price = Chari::quote(['origin_relay_id' => 1, 'destination_relay_id' => 2, 'weight_g' => 2000, 'declared_value' => 50000]);

try {
    $shipment = Chari::createShipment([
        'origin_relay_id' => 1,
        'destination_relay_id' => 2,
        'weight_g' => 2000,
        'declared_value' => 50000,
        'recipient_name' => 'Achta',
        'recipient_phone' => '+23566123456',
        'reference' => 'CMD-104',
    ]);
    $shipment['tracking_code'];
    $shipment['tracking_url'];
} catch (ChariException $e) {
    $e->errorCode; // insufficient_credit, no_line… : basez votre logique dessus
    $e->errors;    // les champs refusés
}

Chari::shipment('CR-7K4M-XP2A');
Chari::cancelShipment('CR-7K4M-XP2A');
Chari::createReturn('CR-7K4M-XP2A');
```

Mettez toujours une `reference` : répéter l'appel ne crée ni ne débite rien, et le paquet peut réessayer seul après une coupure. Sans référence, une coupure lève `ChariConnectionException` : vérifiez avant de recréer l'envoi.

## Notifications

Le paquet crée la route `POST /chari/webhook` et vérifie la signature. Indiquez `https://votre-appli.example/chari/webhook` au coordinateur, puis écoutez l'événement :

```php
use ChariRelais\Laravel\Events\ShipmentStatusChanged;
use Illuminate\Support\Facades\Event;

Event::listen(function (ShipmentStatusChanged $event) {
    Order::where('tracking_code', $event->trackingCode)->update(['chari_status' => $event->status]);
});
```

Une notification peut arriver deux fois ou dans le désordre : votre traitement doit le supporter (`$event->id`, `$event->occurredAt`). Si un écouteur échoue, Chari Relais réessaiera plus tard.

Changer l'adresse : `CHARI_WEBHOOK_PATH`. Pour la configuration complète : `php artisan vendor:publish --tag=chari-config`.

## Vos tests

`Http::fake()` suffit :

```php
Http::fake(['*/api/v1/shipments' => Http::response(['data' => ['tracking_code' => 'CR-TEST']], 201)]);
```

## Tests du paquet

```bash
composer install && vendor/bin/phpunit
```
