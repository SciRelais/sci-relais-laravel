<?php

declare(strict_types=1);

namespace ChariRelais\Laravel\Exceptions;

/** Pas de réponse : on ne sait pas si l'appel a abouti. Sans « reference », vérifiez avant de recréer un envoi. */
class ChariConnectionException extends ChariException {}
