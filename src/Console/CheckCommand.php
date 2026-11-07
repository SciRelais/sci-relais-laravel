<?php

declare(strict_types=1);

namespace SciRelais\Laravel\Console;

use Illuminate\Console\Command;
use RuntimeException;
use SciRelais\Laravel\SciClient;

/** Vérifie l'installation : adresse, clé, secret de notification. Ne crée rien et ne débite rien. */
class CheckCommand extends Command
{
    protected $signature = 'sci:check';

    protected $description = 'Vérifie la connexion à Sci Relais';

    public function handle(SciClient $sci): int
    {
        try {
            $relays = $sci->relays();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Connexion réussie : '.count($relays).' relais disponibles.');

        if (blank(config('sci.webhook_secret'))) {
            $this->warn('SCI_WEBHOOK_SECRET est vide : les notifications seront refusées.');
        }

        return self::SUCCESS;
    }
}
