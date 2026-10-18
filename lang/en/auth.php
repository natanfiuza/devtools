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
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many attempts. Please try again in :seconds seconds.',
    'suspended' => 'This account is suspended. Write to us if you think it is a mistake.',
    'reactivated' => 'Welcome back! The deletion request has been canceled and your account was successfully reactivated.',

    'social' => [
        'divider' => 'or',
        'providers' => [
            'google' => 'Google',
            'github' => 'GitHub',
        ],
        'continue_with' => 'Continue with {provider}',
        'unavailable' => 'Sign in with :provider is not available yet.',
        'failed' => 'We could not complete the sign in with :provider. Please try again.',
        'email_missing' => ':provider did not share an email address. Create an account with email and password instead.',
        'email_unverified' => 'An account with this email already exists. Sign in with your password.',
    ],

    'login' => [
        'title' => 'Log in',
        'description' => 'Welcome back. Sign in to continue.',
        'email' => 'Email',
        'email_placeholder' => "you{'@'}example.com",
        'password' => 'Password',
        'password_placeholder' => 'Your password',
        'forgot' => 'Forgot your password?',
        'remember' => 'Remember me',
        'submit' => 'Log in',
        'no_account' => 'No account yet?',
        'register' => 'Create account',
    ],

    'register' => [
        'title' => 'Create account',
        'description' => 'It takes a minute: name, email and password.',
        'name' => 'Name',
        'name_placeholder' => 'Your name',
        'email' => 'Email',
        'email_placeholder' => "you{'@'}example.com",
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'locale' => 'Main language',
        'locale_help' => 'Used for the emails we send you. You can change it later.',
        'newsletter' => 'Send me the newsletter with news and new tools.',
        'terms' => 'By creating an account you agree to the {terms} and the {privacy}.',
        'terms_link' => 'Terms of Use',
        'privacy_link' => 'Privacy Policy',
        'submit' => 'Create account',
        'have_account' => 'Already have an account?',
        'login' => 'Log in',
    ],

    'forgot' => [
        'title' => 'Forgot your password',
        'description' => 'Tell us your email and we send you a link to create a new password.',
        'email' => 'Email',
        'email_placeholder' => "you{'@'}example.com",
        'submit' => 'Email password reset link',
        'back' => 'Back to log in',
    ],

    'reset' => [
        'title' => 'Create a new password',
        'description' => 'Pick a new password for your account.',
        'email' => 'Email',
        'password' => 'New password',
        'password_confirmation' => 'Confirm the new password',
        'submit' => 'Save password',
    ],

    'verify' => [
        'title' => 'Confirm your email',
        'description' => 'We sent a link to {email}. Open it to unlock your account.',
        'sent' => 'Link sent again. Check your inbox.',
        'unverified' => 'Your email address is unverified.',
        'unverified_action' => 'Re-send the verification email.',
        'resend' => 'Resend the link',
        'logout' => 'Log out',
    ],

    'confirm' => [
        'title' => 'Confirm your password',
        'description' => 'This is a secure area. Confirm your password to continue.',
        'password' => 'Password',
        'submit' => 'Confirm',
        'set_title' => 'Set your password',
        'set_description' => 'Your account was created with social login and has no password yet. Set one to unlock the security settings.',
        'set_password' => 'New password',
        'set_password_confirmation' => 'Confirm the new password',
        'set_submit' => 'Set password',
        'set_success' => 'Password set.',
    ],

    'two_factor' => [
        'title' => 'Two-factor authentication',
        'description' => 'Confirm access to your account by entering the authentication code provided by your authenticator application.',
        'recovery_title' => 'Recovery code',
        'recovery_description' => 'Confirm access to your account by entering one of your emergency recovery codes.',
        'code_label' => '6-digit code',
        'recovery_code_label' => 'Recovery code',
        'submit' => 'Confirm',
        'use_recovery_code' => 'Use a recovery code',
        'use_auth_code' => 'Use an authenticator app code',
    ],
];
