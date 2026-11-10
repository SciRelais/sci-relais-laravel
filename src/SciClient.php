<?php

declare(strict_types=1);

namespace SciRelais\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use RuntimeException;
use SciRelais\Laravel\Exceptions\SciConnectionException;
use SciRelais\Laravel\Exceptions\SciException;

/** Appels à l'API Sci Relais. Chaque méthode renvoie le contenu de « data », ou lève une exception. */
class SciClient
{
    /** @param array{url?: ?string, key?: ?string, timeout?: int, retries?: int} $config */
    public function __construct(private readonly Factory $http, private readonly array $config) {}

    /**
     * Le même client, avec la clé d'un autre compte Sci Relais : celle d'un vendeur d'une place de marché, par exemple.
     * Chaque compte a son crédit, ses factures et ses reversements : l'envoi est débité au compte de cette clé.
     */
    public function forKey(string $key): static
    {
        return new static($this->http, ['key' => $key] + $this->config);
    }

    /**
     * @param  string|null  $country  code du pays (CM), ou « all » ; par défaut celui de votre compte
     * @return list<array<string, mixed>>
     */
    public function relays(?string $city = null, ?string $country = null): array
    {
        return $this->call('get', 'relays', array_filter(['city' => $city, 'country' => $country]), retry: true);
    }

    /**
     * Prix et délai, sans rien créer.
     *
     * @param  array<string, mixed>  $input  origin_relay_id, destination_relay_id, weight_g, declared_value…
     * @return array<string, mixed>
     */
    public function quote(array $input): array
    {
        return $this->call('post', 'quotes', $input, retry: true);
    }

    /**
     * Crée l'envoi et débite votre crédit. Avec une « reference », répéter l'appel renvoie l'envoi existant sans
     * débiter à nouveau. C'est pourquoi seul un appel avec référence est réessayé après une coupure.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function createShipment(array $input): array
    {
        return $this->call('post', 'shipments', $input, retry: filled($input['reference'] ?? null));
    }

    /** @return array<string, mixed> l'envoi et son historique dans « events » */
    public function shipment(string $trackingCode): array
    {
        return $this->call('get', 'shipments/'.rawurlencode($trackingCode), retry: true);
    }

    /** @return array<string, mixed> Annule avant dépôt et rend le crédit ; ensuite : erreur not_cancellable. */
    public function cancelShipment(string $trackingCode): array
    {
        return $this->call('delete', 'shipments/'.rawurlencode($trackingCode));
    }

    /** @return array<string, mixed> Le retour d'un client, pour un colis déjà remis. */
    public function createReturn(string $trackingCode): array
    {
        return $this->call('post', 'shipments/'.rawurlencode($trackingCode).'/return');
    }

    /**
     * Sci Express, prix d'une course en ville, sans rien créer.
     *
     * @param  array<string, mixed>  $input  city, et si connues pickup{lat,lng} et dropoff{lat,lng} (sinon : forfait de la ville)
     * @return array<string, mixed> price{amount, currency}, distance_m…
     */
    public function expressQuote(array $input): array
    {
        return $this->call('post', 'express/quotes', $input, retry: true);
    }

    /**
     * Commande une course express et débite votre crédit (rendu si aucun coursier ne la prend ou si vous l'annulez avant
     * le départ). Avec une « reference », répéter l'appel renvoie la course existante : seul cet appel est réessayé.
     *
     * @param  array<string, mixed>  $input  reference, city, pickup{name,phone,address,lat,lng}, dropoff{name,phone,address,lat,lng,note}, contents, recipient_locale
     * @return array<string, mixed> code, status, price, delivery_code, tracking_url, courier…
     */
    public function createExpressDelivery(array $input): array
    {
        return $this->call('post', 'express/deliveries', $input, retry: filled($input['reference'] ?? null));
    }

    /** @return array<string, mixed> la course, son coursier (et sa position pendant la course) et son historique dans « events » */
    public function expressDelivery(string $code): array
    {
        return $this->call('get', 'express/deliveries/'.rawurlencode($code), retry: true);
    }

    /** @return array<string, mixed> Annule tant que le coursier n'est pas parti avec la commande ; ensuite : erreur not_cancellable. */
    public function cancelExpressDelivery(string $code): array
    {
        return $this->call('delete', 'express/deliveries/'.rawurlencode($code));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    private function call(string $method, string $path, array $data = [], bool $retry = false): array
    {
        try {
            /** @var Response $response */
            $response = $this->request($retry)->{$method}($path, $data);
        } catch (ConnectionException $e) {
            throw new SciConnectionException('Sci Relais ne répond pas : '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw SciException::fromResponse($response);
        }

        return (array) $response->json('data', []);
    }

    private function request(bool $retry): PendingRequest
    {
        $url = $this->config['url'] ?? null;
        $key = $this->config['key'] ?? null;
        if (blank($url) || blank($key)) {
            throw new RuntimeException('Sci Relais : renseignez SCI_URL et SCI_KEY dans votre fichier .env, ou passez la clé du compte avec Sci::forKey().');
        }

        $request = $this->http->baseUrl(rtrim((string) $url, '/').'/api/v1/')
            ->withToken((string) $key)
            ->acceptJson()
            ->withHeaders(['Accept-Language' => app()->getLocale()])
            ->timeout((int) ($this->config['timeout'] ?? 15));

        $retries = (int) ($this->config['retries'] ?? 0);

        return $retry && $retries > 0
            ? $request->retry($retries + 1, 500, fn ($e): bool => $e instanceof ConnectionException, throw: false)
            : $request;
    }
}
