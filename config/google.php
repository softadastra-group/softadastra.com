<?php

/**
 * Google OAuth Configuration
 *
 * Loaded via the Loader from .env and injected into Config.
 *
 * Options:
 *  - client_id     : Google OAuth Client ID
 *  - client_secret : Google OAuth Client Secret
 *  - redirect_uri  : URL where Google will redirect after authentication
 *  - scopes        : Array of scopes requested from Google (default: email, profile)
 *
 * Usage:
 *   $config = config_value('google');
 *   $googleService = new GoogleService($config);
 */

return [
    'client_id'     => GOOGLE_CLIENT_ID ?? '',
    'client_secret' => GOOGLE_CLIENT_SECRET ?? '',
    'redirect_uri'  => GOOGLE_REDIRECT_URI ?? '',
    'scopes'        => GOOGLE_SCOPES
        ? array_map('trim', explode(',', GOOGLE_SCOPES))
        : ['email', 'profile'],
];
