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

    'tradingview' => [
        'cdp_url' => env('TRADINGVIEW_CDP_URL', 'http://127.0.0.1:9222'),
        'app_path' => env('TRADINGVIEW_APP_PATH', '/Applications/TradingView.app/Contents/MacOS/TradingView'),
        'cli_path' => env('TRADINGVIEW_CLI_PATH', '/Users/adybudi/tradingview-mcp/src/cli/index.js'),
        'timeout' => (int) env('TRADINGVIEW_TIMEOUT', 10),
    ],

    'omniroute' => [
        'url' => env('OMNIROUTE_URL', 'http://127.0.0.1:20128/v1'),
        'api_key' => env('OMNIROUTE_API_KEY', ''),
        'model' => env('OMNIROUTE_MODEL', 'gemini-flash-3.8-antigravity'),
        'timeout' => (int) env('OMNIROUTE_TIMEOUT', 15),
        'enabled' => (bool) env('OMNIROUTE_ENABLED', true),
    ],


];
