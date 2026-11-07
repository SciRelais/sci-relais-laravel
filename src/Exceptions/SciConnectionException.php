<?php

declare(strict_types=1);

namespace SciRelais\Laravel\Exceptions;

/** Pas de réponse : on ne sait pas si l'appel a abouti. Sans « reference », vérifiez avant de recréer un envoi. */
class SciConnectionException extends SciException {}
