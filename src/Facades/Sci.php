<?php

declare(strict_types=1);

namespace SciRelais\Laravel\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use SciRelais\Laravel\SciClient;
use SciRelais\Laravel\WebhookSecrets;

/**
 * @method static array relays(?string $city = null, ?string $country = null)
 * @method static array quote(array $input)
 * @method static array createShipment(array $input)
 * @method static array shipment(string $trackingCode)
 * @method static array cancelShipment(string $trackingCode)
 * @method static array createReturn(string $trackingCode)
 * @method static array expressQuote(array $input)
 * @method static array createExpressDelivery(array $input)
 * @method static array expressDelivery(string $code)
 * @method static array cancelExpressDelivery(string $code)
 * @method static SciClient forKey(string $key)
 *
 * @see SciClient
 */
class Sci extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SciClient::class;
    }

    /**
     * Plusieurs comptes : retrouvez le secret d'un compte depuis l'identifiant de son adresse de notification.
     *
     * @param  Closure(string): ?string  $resolver
     */
    public static function webhookSecretsUsing(Closure $resolver): void
    {
        app(WebhookSecrets::class)->using($resolver);
    }
}
