<?php

use App\Enums\Auth\AdminAuthProvider;

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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => "/auth/admin/callback/" . AdminAuthProvider::GOOGLE->value,
    ],

    'api_bible' => [
        'key' => env('API_BIBLE_KEY'),
        'base_url' => env('API_BIBLE_BASE_URL'),
    ],

    'olie_flow' => [
        'base_url' => env('OLIE_FLOW_BASE_URL'),
        'api_key' => env('OLIE_FLOW_API_KEY'),
        'step_id' => env('OLIE_FLOW_STEP_ID'),
        'form_id' => env('OLIE_FLOW_FORM_ID'),
        'edge_type_id' => env('OLIE_FLOW_EDGE_TYPE_ID'),
        'edge_description_id' => env('OLIE_FLOW_EDGE_DESCRIPTION_ID'),
        'edge_files_id' => env('OLIE_FLOW_EDGE_FILES_ID'),
    ],

];
