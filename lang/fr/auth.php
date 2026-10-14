<?php

/*
| Strings das telas de acesso (namespace "auth" do vue-i18n) e as chaves que o
| Fortify consome no servidor: failed, password e throttle.
|
| Convencao de interpolacao: chaves lidas pelo servidor usam ":param" (Laravel);
| chaves lidas pelo Vue usam "{param}" (vue-i18n). Nenhuma chave e lida pelos
| dois lados quando tem parametro.
*/

return [
    // Consumidas pelo Fortify (servidor).
    'failed' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
    'password' => 'Le mot de passe fourni est incorrect.',
    'throttle' => 'Trop de tentatives. Réessayez dans :seconds secondes.',
    'suspended' => 'Ce compte est suspendu. Écrivez-nous si vous pensez qu\'il s\'agit d\'une erreur.',

    'social' => [
        'divider' => 'ou',
        'providers' => [
            'google' => 'Google',
            'github' => 'GitHub',
        ],
        'continue_with' => 'Continuer avec {provider}',
        'unavailable' => 'La connexion avec :provider n’est pas encore disponible.',
        'failed' => 'Nous n’avons pas pu terminer la connexion avec :provider. Réessayez.',
        'email_missing' => ':provider n’a pas partagé d’adresse e-mail. Créez plutôt un compte avec e-mail et mot de passe.',
        'email_unverified' => 'Un compte existe déjà avec cet e-mail. Connectez-vous avec votre mot de passe.',
    ],

    'login' => [
        'title' => 'Connexion',
        'description' => 'Content de vous revoir. Connectez-vous pour continuer.',
        'email' => 'E-mail',
        'email_placeholder' => "vous{'@'}exemple.com",
        'password' => 'Mot de passe',
        'password_placeholder' => 'Votre mot de passe',
        'forgot' => 'Mot de passe oublié ?',
        'remember' => 'Se souvenir de moi',
        'submit' => 'Se connecter',
        'no_account' => 'Pas encore de compte ?',
        'register' => 'Créer un compte',
    ],

    'register' => [
        'title' => 'Créer un compte',
        'description' => 'Une minute suffit : nom, e-mail et mot de passe.',
        'name' => 'Nom',
        'name_placeholder' => 'Votre nom',
        'email' => 'E-mail',
        'email_placeholder' => "vous{'@'}exemple.com",
        'password' => 'Mot de passe',
        'password_confirmation' => 'Confirmer le mot de passe',
        'locale' => 'Langue principale',
        'locale_help' => 'Utilisée pour les e-mails que nous vous envoyons. Vous pourrez la modifier plus tard.',
        'newsletter' => 'Je veux recevoir la newsletter avec les nouveautés et les nouveaux outils.',
        'terms' => 'En créant un compte, vous acceptez les {terms} et la {privacy}.',
        'terms_link' => 'Conditions d’utilisation',
        'privacy_link' => 'Politique de confidentialité',
        'submit' => 'Créer un compte',
        'have_account' => 'Vous avez déjà un compte ?',
        'login' => 'Se connecter',
    ],

    'forgot' => [
        'title' => 'Mot de passe oublié',
        'description' => 'Indiquez votre e-mail et nous envoyons le lien pour créer un nouveau mot de passe.',
        'email' => 'E-mail',
        'email_placeholder' => "vous{'@'}exemple.com",
        'submit' => 'Envoyer le lien',
        'back' => 'Retour à la connexion',
    ],

    'reset' => [
        'title' => 'Créer un nouveau mot de passe',
        'description' => 'Choisissez un nouveau mot de passe pour votre compte.',
        'email' => 'E-mail',
        'password' => 'Nouveau mot de passe',
        'password_confirmation' => 'Confirmer le nouveau mot de passe',
        'submit' => 'Enregistrer le mot de passe',
    ],

    'verify' => [
        'title' => 'Confirmez votre e-mail',
        'description' => 'Nous avons envoyé un lien à {email}. Ouvrez-le pour activer votre compte.',
        'sent' => 'Lien renvoyé. Vérifiez votre boîte de réception.',
        'unverified' => 'Votre adresse e-mail n’est pas encore vérifiée.',
        'unverified_action' => 'Renvoyer l’e-mail de vérification.',
        'resend' => 'Renvoyer le lien',
        'logout' => 'Se déconnecter',
    ],

    'confirm' => [
        'title' => 'Confirmez votre mot de passe',
        'description' => 'Zone sensible. Confirmez votre mot de passe pour continuer.',
        'password' => 'Mot de passe',
        'submit' => 'Confirmer',
        'set_title' => 'Définissez votre mot de passe',
        'set_description' => 'Votre compte a été créé avec une connexion sociale et n’a pas encore de mot de passe. Définissez-en un pour débloquer les paramètres de sécurité.',
        'set_password' => 'Nouveau mot de passe',
        'set_password_confirmation' => 'Confirmer le nouveau mot de passe',
        'set_submit' => 'Définir le mot de passe',
        'set_success' => 'Mot de passe défini.',
    ],
];
