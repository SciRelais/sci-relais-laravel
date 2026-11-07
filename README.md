# Sci Relais pour Laravel

Créez et suivez vos envois Sci Relais depuis votre application, et recevez leurs changements d'état. Laravel 11, 12 ou 13, PHP 8.2 ou plus.

## Installation

Le paquet n'est pas encore publié sur Packagist. Téléchargez le fichier zip depuis le guide d'intégration de Sci Relais, placez-le dans un dossier `packages/` à la racine de votre application, puis :

```bash
composer config repositories.sci artifact ./packages
composer require sci-relais/laravel
```

Dans `.env` :

```
SCI_URL=https://votre-domaine-sci.example
SCI_KEY=votre-cle
SCI_WEBHOOK_SECRET=votre-secret
```

Vérifiez, sans rien créer ni débiter : `php artisan sci:check`

## Utilisation

```php
use SciRelais\Laravel\Exceptions\SciException;
use SciRelais\Laravel\Facades\Sci;

$relays = Sci::relays(city: 'Moundou');

$price = Sci::quote(['origin_relay_id' => 1, 'destination_relay_id' => 2, 'weight_g' => 2000, 'declared_value' => 50000]);

try {
    $shipment = Sci::createShipment([
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
} catch (SciException $e) {
    $e->errorCode; // insufficient_credit, no_line… : basez votre logique dessus
    $e->errors;    // les champs refusés
}

Sci::shipment('SR-7K4M-XP2A');
Sci::cancelShipment('SR-7K4M-XP2A');
Sci::createReturn('SR-7K4M-XP2A');
```

Mettez toujours une `reference` : répéter l'appel ne crée ni ne débite rien, et le paquet peut réessayer seul après une coupure. Sans référence, une coupure lève `SciConnectionException` : vérifiez avant de recréer l'envoi.

## Notifications

Le paquet crée la route `POST /sci/webhook` et vérifie la signature. Indiquez `https://votre-appli.example/sci/webhook` au coordinateur, puis écoutez l'événement :

```php
use SciRelais\Laravel\Events\ShipmentStatusChanged;
use Illuminate\Support\Facades\Event;

Event::listen(function (ShipmentStatusChanged $event) {
    Order::where('tracking_code', $event->trackingCode)->update(['sci_status' => $event->status]);
});
```

Une notification peut arriver deux fois ou dans le désordre : votre traitement doit le supporter (`$event->id`, `$event->occurredAt`). Si un écouteur échoue, Sci Relais réessaiera plus tard.

Changer l'adresse : `SCI_WEBHOOK_PATH`. Pour la configuration complète : `php artisan vendor:publish --tag=sci-config`.

## Vos tests

`Http::fake()` suffit :

```php
Http::fake(['*/api/v1/shipments' => Http::response(['data' => ['tracking_code' => 'SR-TEST']], 201)]);
```

## Tests du paquet

```bash
composer install && vendor/bin/phpunit
```
