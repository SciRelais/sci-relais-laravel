<?php

return [
    // Adresse de la plateforme, sans /api/v1 : https://votre-domaine-chari.example
    'url' => env('CHARI_URL'),

    // Clé d'API de votre compte entreprise, générée dans l'administration de Chari Relais.
    'key' => env('CHARI_KEY'),

    // Secret de notification, généré avec la clé. Sert à vérifier que les notifications viennent bien de Chari Relais.
    'webhook_secret' => env('CHARI_WEBHOOK_SECRET'),

    // Adresse de votre appli qui reçoit les notifications. null : aucune route n'est créée.
    'webhook_path' => env('CHARI_WEBHOOK_PATH', 'chari/webhook'),

    // Écart accepté, en secondes, avec l'horodatage d'une notification.
    'webhook_tolerance' => 300,

    'timeout' => 15,

    // Nouveaux essais après une coupure réseau, seulement quand répéter l'appel est sans danger.
    'retries' => 2,
];
