# Sci Relais pour Laravel

Créez et suivez vos envois Sci Relais et vos courses Sci Express depuis votre application, et recevez leurs changements d'état. Laravel 11, 12 ou 13, PHP 8.2 ou plus.

## Installation

```bash
composer config repositories.sci-relais vcs https://github.com/SciRelais/sci-relais-laravel
composer require sci-relais/laravel
```

Sans accès à GitHub depuis le serveur : téléchargez le fichier zip depuis le guide d'intégration de Sci Relais, placez-le dans un dossier `packages/` à la racine de votre application, puis `composer config repositories.sci artifact ./packages` et `composer require sci-relais/laravel`.

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

## Sci Express : courses en ville

Un coursier récupère une commande (un repas, par exemple) et la porte au client, dans la même ville. Le prix est débité de votre crédit à la commande et rendu si aucun coursier ne la prend ou si vous annulez avant le départ.

```php
$price = Sci::expressQuote(['city' => "N'Djamena", 'pickup' => ['lat' => 12.11, 'lng' => 15.04], 'dropoff' => ['lat' => 12.118, 'lng' => 15.043]]);

$run = Sci::createExpressDelivery([
    'reference' => 'CMD-104',
    'city' => "N'Djamena",
    'pickup' => ['name' => 'Le Palmier', 'phone' => '66770011', 'address' => 'Avenue Charles de Gaulle', 'lat' => 12.11, 'lng' => 15.04],
    'dropoff' => ['name' => 'Awa', 'phone' => '66223344', 'address' => 'Moursal, porte bleue', 'lat' => 12.118, 'lng' => 15.043, 'note' => 'Appeler en arrivant'],
    'contents' => '2 plats',
]);
$run['code'];          // EX-7K4M-XP2A
$run['tracking_url'];  // page de suivi du client, avec la position du coursier
$run['delivery_code']; // le client le donne au coursier à la remise (il le reçoit aussi par SMS)

Sci::expressDelivery('EX-7K4M-XP2A');
Sci::cancelExpressDelivery('EX-7K4M-XP2A');
```

Sans positions, c'est le forfait de la ville ; une ville sans forfait refuse avec `coordinates_required`. Autres refus : `no_rate`, `too_far`, `insufficient_credit`, `not_cancellable`.

## Notifications

Le paquet crée la route `POST /sci/webhook` et vérifie la signature. Indiquez `https://votre-appli.example/sci/webhook` au coordinateur, puis écoutez l'événement :

```php
use SciRelais\Laravel\Events\ShipmentStatusChanged;
use Illuminate\Support\Facades\Event;

Event::listen(function (ShipmentStatusChanged $event) {
    Order::where('tracking_code', $event->trackingCode)->update(['sci_status' => $event->status]);
});
```

Les courses express ont leur événement, avec le coursier dès qu'il a la course :

```php
use SciRelais\Laravel\Events\ExpressStatusChanged;

Event::listen(function (ExpressStatusChanged $event) {
    // $event->code, $event->reference, $event->status (assigned, picked_up, delivered…), $event->courier['name'] ?? null
});
```

Une notification peut arriver deux fois ou dans le désordre : votre traitement doit le supporter (`$event->id`, `$event->occurredAt`). Si un écouteur échoue, Sci Relais réessaiera plus tard.

Changer l'adresse : `SCI_WEBHOOK_PATH`. Pour la configuration complète : `php artisan vendor:publish --tag=sci-config`.

## Plusieurs comptes : une place de marché

Si chaque vendeur de votre application a son propre compte Sci Relais (son crédit, ses factures, ses reversements de contre-remboursement), gardez la clé de chaque vendeur dans votre base, chiffrée, et appelez avec elle :

```php
$shipment = Sci::forKey($shop->sci_key)->createShipment([...]);
$price = Sci::forKey($shop->sci_key)->quote([...]);
```

`SCI_KEY` peut alors rester vide. Vérifier la clé d'un vendeur : `php artisan sci:check --key=…`

Notifications : chaque vendeur a sa propre adresse, avec un identifiant que vous choisissez (lettres, chiffres, `-` et `_`), par exemple `https://votre-appli.example/sci/webhook/boutique-12`. Indiquez-la au coordinateur pour ce compte, avec son secret. Dans un fournisseur de services, dites au paquet comment retrouver le secret :

```php
use SciRelais\Laravel\Facades\Sci;

Sci::webhookSecretsUsing(fn (string $account): ?string => Shop::where('sci_account', $account)->value('sci_webhook_secret'));
```

Un compte inconnu, ou une notification signée avec le secret d'un autre compte, est refusé. L'événement porte le compte : `$event->account` (`null` sur l'adresse unique du fichier `.env`).

## Vos tests

`Http::fake()` suffit :

```php
Http::fake(['*/api/v1/shipments' => Http::response(['data' => ['tracking_code' => 'SR-TEST']], 201)]);
```

## Tests du paquet

```bash
composer install && vendor/bin/phpunit
```
