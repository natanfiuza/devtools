<?php

return [
    'title' => 'Paramètres',
    'subtitle' => 'Gérez votre profil et les paramètres de votre compte',

    'nav' => [
        'profile' => 'Profil',
        'security' => 'Sécurité',
        'appearance' => 'Apparence',
    ],

    'profile' => [
        'title' => 'Profil',
        'description' => 'Mettez à jour votre nom, votre identifiant et votre adresse e-mail',
        'name' => 'Nom',
        'name_placeholder' => 'Nom complet',
        'username' => 'Nom d’utilisateur',
        'username_placeholder' => 'nomdutilisateur',
        'username_help' => 'Lettres minuscules, chiffres et tiret bas uniquement (3 à 30 caractères). Utilisé comme identifiant unique sur la plateforme.',
        'email' => 'Adresse e-mail',
        'email_placeholder' => "vous{'@'}exemple.com",
        'locale' => 'Langue du compte',
        'locale_help' => 'La langue des e-mails que nous vous envoyons. L’interface suit la langue dans laquelle vous naviguez.',
        'save' => 'Enregistrer',
    ],

    'security' => [
        'title' => 'Sécurité',
        'description' => 'Gardez votre compte sur un mot de passe long et aléatoire.',
        'current_password' => 'Mot de passe actuel',
        'current_password_placeholder' => 'Mot de passe actuel',
        'password' => 'Nouveau mot de passe',
        'password_placeholder' => 'Nouveau mot de passe',
        'password_confirmation' => 'Confirmez le nouveau mot de passe',
        'password_confirmation_placeholder' => 'Confirmez le nouveau mot de passe',
        'save' => 'Enregistrer',
    ],

    'appearance' => [
        'title' => 'Apparence',
        'description' => 'Choisissez l’apparence de devtools sur cet appareil.',
    ],
];
