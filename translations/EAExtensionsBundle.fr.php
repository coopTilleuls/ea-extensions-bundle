<?php

return [
    'pagination' => [
        'per_page' => 'par page',
    ],
    'crud_export' => [
        'action_label' => 'Exporter',
        'limit_exceeded' => 'Le nombre de résultats à exporter (%count%) dépasse la limite autorisée (%limit%). Veuillez affiner votre sélection.',
        'async_in_progress' => "Le fichier d'export est en cours de préparation. Vous recevrez une notification lorsqu'il sera disponible.",
    ],
    'notifications' => [
        'status' => [
            'read' => 'Lu',
            'unread' => 'Non lu',
            'treatment_done' => 'Demande traitée',
            'treatment_pending' => 'Non traitée',
        ],
        'bar' => [
            'label' => 'Notifications',
        ],
        'popover' => [
            'new_notification' => 'Nouvelle notification',
            'close' => 'Fermer',
        ],
        'card' => [
            'title' => 'Dernières notifications',
            'see_all' => 'Voir tout',
            'view' => 'Voir',
            'empty' => 'Aucune notification non lue.',
        ],
        'detail' => [
            'default_button_label' => 'Voir la fiche',
        ],
    ],
    'add_button_select' => [
        'label' => 'Ajouter',
    ],
    'tab_navigation' => [
        'previous' => 'Onglet précédent',
        'next' => 'Onglet suivant',
    ],
    'back_to_top' => [
        'label' => 'Retour en haut de page',
    ],
];
