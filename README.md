# Sci Relais pour Laravel

Créez et suivez vos envois Sci Relais depuis votre application, et recevez leurs changements d'état. Laravel 11, 12 ou 13, PHP 8.2 ou plus.

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
