<?php

return [
    'title' => 'Settings',
    'subtitle' => 'Manage your profile and account settings',

    'nav' => [
        'profile' => 'Profile',
        'security' => 'Security',
        'appearance' => 'Appearance',
    ],

    'profile' => [
        'title' => 'Profile',
        'description' => 'Update your name and email address',
        'name' => 'Name',
        'name_placeholder' => 'Full name',
        'email' => 'Email address',
        'email_placeholder' => "you{'@'}example.com",
        'locale' => 'Account language',
        'locale_help' => 'The language of the emails we send you. The interface follows the language you are browsing in.',
        'save' => 'Save',
    ],

    'security' => [
        'title' => 'Security',
        'description' => 'Keep your account on a long, random password.',
        'current_password' => 'Current password',
        'current_password_placeholder' => 'Current password',
        'password' => 'New password',
        'password_placeholder' => 'New password',
        'password_confirmation' => 'Confirm the new password',
        'password_confirmation_placeholder' => 'Confirm the new password',
        'save' => 'Save',
    ],

    'appearance' => [
        'title' => 'Appearance',
        'description' => 'Choose how devtools looks on this device.',
    ],
];
