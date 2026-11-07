<?php

declare(strict_types=1);

namespace ChariRelais\Laravel\Console;

use ChariRelais\Laravel\ChariClient;
use Illuminate\Console\Command;
use RuntimeException;

/** Vérifie l'installation : adresse, clé, secret de notification. Ne crée rien et ne débite rien. */
class CheckCommand extends Command
{
    protected $signature = 'chari:check';

    protected $description = 'Vérifie la connexion à Chari Relais';

    public function handle(ChariClient $chari): int
    {
        try {
            $relays = $chari->relays();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Connexion réussie : '.count($relays).' relais disponibles.');

        if (blank(config('chari.webhook_secret'))) {
            $this->warn('CHARI_WEBHOOK_SECRET est vide : les notifications seront refusées.');
        }

        return self::SUCCESS;
    }
}
