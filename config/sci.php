<?php

return [
    // Adresse de la plateforme, sans /api/v1 : https://votre-domaine-sci.example
    'url' => env('SCI_URL'),

    // Clé d'API de votre compte entreprise, générée dans l'administration de Sci Relais.
    'key' => env('SCI_KEY'),

    // Secret de notification, généré avec la clé. Sert à vérifier que les notifications viennent bien de Sci Relais.
    'webhook_secret' => env('SCI_WEBHOOK_SECRET'),

    // Adresse de votre appli qui reçoit les notifications. null : aucune route n'est créée.
    'webhook_path' => env('SCI_WEBHOOK_PATH', 'sci/webhook'),

    // Écart accepté, en secondes, avec l'horodatage d'une notification.
    'webhook_tolerance' => 300,

    'timeout' => 15,

    // Nouveaux essais après une coupure réseau, seulement quand répéter l'appel est sans danger.
    'retries' => 2,
];
