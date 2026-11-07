<?php

declare(strict_types=1);

namespace ChariRelais\Laravel\Facades;

use ChariRelais\Laravel\ChariClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array relays(?string $city = null, ?string $country = null)
 * @method static array quote(array $input)
 * @method static array createShipment(array $input)
 * @method static array shipment(string $trackingCode)
 * @method static array cancelShipment(string $trackingCode)
 * @method static array createReturn(string $trackingCode)
 *
 * @see ChariClient
 */
class Chari extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ChariClient::class;
    }
}
