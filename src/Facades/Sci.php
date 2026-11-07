<?php

declare(strict_types=1);

namespace SciRelais\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use SciRelais\Laravel\SciClient;

/**
 * @method static array relays(?string $city = null, ?string $country = null)
 * @method static array quote(array $input)
 * @method static array createShipment(array $input)
 * @method static array shipment(string $trackingCode)
 * @method static array cancelShipment(string $trackingCode)
 * @method static array createReturn(string $trackingCode)
 *
 * @see SciClient
 */
class Sci extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SciClient::class;
    }
}
