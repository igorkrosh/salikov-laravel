<?php 
    return [
        'key' => env('NOTISEND_API_KEY', ''),
        'sms_key' => env('NOTISEND_SMS_API_KEY', ''),
        'sms_project' => env('NOTISEND_SMS_PROJECT_NAME', ''),
        'sms_sender' => env('NOTISEND_SMS_SENDER', ''),
    ];