<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TorobPay Gateway Credentials
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for TorobPay CPG.
    | You should obtain these credentials from TorobPay and place them
    | in your .env file for security.
    |
    */

    'base_url' => env('TOROBPAY_BASE_URL', 'https://cpg.torobpay.com'),

    'client_id' => env('TOROBPAY_CLIENT_ID'),
    
    'client_secret' => env('TOROBPAY_CLIENT_SECRET'),

    'username' => env('TOROBPAY_USERNAME'),

    'password' => env('TOROBPAY_PASSWORD'),
];
