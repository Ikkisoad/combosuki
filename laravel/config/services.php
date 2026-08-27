<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discord' => [
        'public_key' => env('DISCORD_PUBLIC_KEY'),
        'application_id' => env('DISCORD_APPLICATION_ID'),
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'guild_id' => env('DISCORD_GUILD_ID'),

        // OAuth2 (account linking). Socialite reads these three key names
        // verbatim. The redirect is set explicitly rather than derived from
        // route() because the site is served from the repo root through an
        // index.php/.htaccess bridge into laravel/public, and Discord
        // requires the redirect_uri to match the portal entry exactly.
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI'),

        // Sign-in/sign-up uses a second, separate callback so the guest flow
        // and the authenticated linking flow never share an endpoint. Both
        // URLs must be registered in the Discord portal.
        'auth_redirect' => env('DISCORD_AUTH_REDIRECT_URI'),
    ],

];
