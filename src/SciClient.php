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
            throw new RuntimeException('Sci Relais : renseignez SCI_URL et SCI_KEY dans votre fichier .env.');
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
