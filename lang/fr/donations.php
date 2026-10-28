<?php

/*
| Dons via Pix (F6). Namespace du frontend : la page publique et la page de
| statut. L'e-mail du reçu vit dans mail.php (serveur uniquement).
*/

return [
    'title' => 'Faire un don',
    'subtitle' => 'Le site est gratuit et le reste. S\'il vous aide au quotidien, un don de n\'importe quel montant permet de garder les outils en ligne.',
    'meta' => [
        'title' => 'Faire un don à devtools',
        'description' => 'Soutenez devtools par un don via Pix. Tout montant aide à garder les outils gratuits et en ligne.',
    ],

    'amount' => [
        'label' => 'Montant du don',
        'suggested' => 'Montants suggérés',
        'custom' => 'Autre montant',
        'custom_hint' => 'Entre {min} et {max}.',
        'invalid' => 'Choisissez un montant entre {min} et {max}.',
    ],

    'donor' => [
        'title' => 'Qui fait le don',
        'name' => 'Nom (facultatif)',
        'name_placeholder' => 'Comment vous souhaitez apparaître',
        'email' => 'E-mail pour le reçu (facultatif)',
        'email_hint' => 'Nous utilisons l\'e-mail uniquement pour envoyer le reçu de ce don.',
        'anonymous' => 'Donner anonymement',
        'anonymous_hint' => 'Sans nom. L\'e-mail, s\'il est fourni, ne sert qu\'au reçu.',
    ],

    'submit' => 'Générer le Pix',
    'processing' => 'Génération du Pix…',

    'info' => [
        'title' => 'Don indisponible pour le moment',
        'description_lead' => 'Pix n\'est pas encore configuré ici. Revenez plus tard ou écrivez-nous depuis la',
        'contact_link' => 'page de contact',
        'description' => 'Pix n\'est pas encore configuré ici. Revenez plus tard ou écrivez-nous depuis la page de contact.',
    ],

    'pix' => [
        'title' => 'Payer avec Pix',
        'description' => 'Ouvrez l\'application de votre banque, choisissez Pix et scannez le QR ou collez le code ci-dessous.',
        'copy' => 'Copier le code Pix',
        'copied' => 'Code copié.',
        'copy_failed' => 'Impossible de copier. Sélectionnez le code ci-dessous.',
        'ticket' => 'Ouvrir dans l\'application bancaire',
        'dynamic_note' => 'La confirmation arrive automatiquement dès que le paiement est approuvé.',
        'static_note' => 'Il s\'agit d\'un Pix statique : pas de confirmation automatique. Conservez votre reçu.',
    ],

    'status' => [
        'title' => 'Votre don',
        'amount' => 'Montant',
        'created_at' => 'Créé le',
        'expires_at' => 'Valable jusqu\'au',
        'pending' => 'En attente de paiement',
        'paid' => 'Don confirmé',
        'expired' => 'Pix expiré',
        'refunded' => 'Don remboursé',
        'failed' => 'Don non finalisé',
        'pending_hint' => 'Dès que le paiement est approuvé, cette page affiche la confirmation.',
        'paid_hint' => 'Merci de soutenir le projet ! Un don ne donne pas accès aux forfaits et ne consomme pas de quota : c\'est un soutien au site.',
        'expired_hint' => 'Le délai du QR est passé et rien n\'a été débité. Vous pouvez générer un nouveau don quand vous voulez.',
        'refunded_hint' => 'Le montant de ce don a été remboursé.',
        'failed_hint' => 'Le paiement n\'a pas été finalisé. Vous pouvez réessayer quand vous voulez.',
        'new' => 'Faire un autre don',
        'receipt' => 'Si vous avez indiqué un e-mail, le reçu arrivera dans votre boîte de réception.',
    ],

    // Motivo affiché au donateur quand un don échoue (ADR-059). La clé vient du
    // `status_detail` ; le code brut ne quitte jamais le serveur.
    'reasons' => [
        'high_risk' => 'Mercado Pago a refusé le Pix lors de son analyse de risque. Réessayez ou utilisez un autre moyen de paiement.',
        'bank' => 'Votre banque a refusé le Pix. Vérifiez votre limite, votre solde ou vos blocages, puis réessayez.',
        'regulations' => 'Une règle réglementaire a bloqué le Pix. Réessayez plus tard ou utilisez un autre compte.',
        'insufficient_data' => 'Il manquait des données à Mercado Pago pour finaliser le Pix. Réessayez avec vos informations complètes.',
        'biz_rule' => 'Une règle du compte qui reçoit le don a refusé le Pix. Réessayez plus tard.',
        'other' => 'Le prestataire n\'a pas finalisé le paiement. Générez un nouveau Pix et réessayez.',
    ],

    'check' => [
        'button' => 'J\'ai déjà payé',
        'loading' => 'Vérification…',
        'hint' => 'Vous avez déjà payé ? Touchez pour confirmer maintenant.',
        'success' => 'Paiement confirmé. Merci !',
        'pending' => 'Mercado Pago n\'a pas encore confirmé ce Pix. Patientez un instant et réessayez.',
        'failed' => 'Le paiement a été refusé. Voir le motif sur cette page.',
        'refunded' => 'Le paiement apparaît comme remboursé.',
        'already' => 'Ce don a déjà été résolu.',
        'unavailable' => 'Il n\'y a rien à vérifier pour ce don pour le moment.',
        'mismatch' => 'Le paiement consulté n\'appartient pas à ce don.',
        'error' => 'Impossible de vérifier le paiement maintenant. Réessayez dans un instant.',
        'throttled' => 'Trop de vérifications en peu de temps. Attendez une minute et réessayez.',
    ],

    'errors' => [
        'unavailable' => 'Les dons via Pix ne sont pas disponibles pour le moment.',
        'gateway' => 'Impossible de générer le Pix maintenant. Réessayez dans un instant.',
        'payer' => 'Impossible de générer le Pix sans e-mail de contact. Réessayez en indiquant votre e-mail.',
    ],

    'pix_description' => 'Don pour :app',
];
