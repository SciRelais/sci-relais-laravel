<?php

declare(strict_types=1);

namespace SciRelais\Laravel\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/** Refus de l'API. Basez votre logique sur $errorCode (insufficient_credit, no_line…) : il ne change pas, le message si. */
class SciException extends RuntimeException
{
    /** @param array<string, mixed> $errors les champs refusés, pour une erreur de validation */
    public function __construct(string $message, public readonly ?string $errorCode = null, public readonly int $status = 0, public readonly array $errors = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        return new self(
            (string) ($response->json('error.message') ?? $response->json('message') ?? 'Erreur '.$response->status()),
            $response->json('error.code'),
            $response->status(),
            (array) $response->json('errors', []),
        );
    }
}
