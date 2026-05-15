<?php

return [
    'attendance' => [
        'require_photo' => env('ATTENDANCE_REQUIRE_PHOTO', false),
        'allowed_distance_meters' => env('ATTENDANCE_ALLOWED_DISTANCE_METERS', 250),
        'allow_manual_correction' => env('ATTENDANCE_ALLOW_MANUAL_CORRECTION', false),
    ],
    'company' => [
        'name' => env('COMPANY_NAME', '鐵皮屋工程管理系統'),
        'phone' => env('COMPANY_PHONE', ''),
        'address' => env('COMPANY_ADDRESS', ''),
        'tax_id' => env('COMPANY_TAX_ID', ''),
    ],
    'quotation' => [
        'default_terms' => env('QUOTATION_DEFAULT_TERMS', ''),
    ],
    'payment' => [
        'bank_name' => env('PAYMENT_BANK_NAME', ''),
        'bank_code' => env('PAYMENT_BANK_CODE', ''),
        'account_number' => env('PAYMENT_ACCOUNT_NUMBER', ''),
        'account_name' => env('PAYMENT_ACCOUNT_NAME', ''),
    ],
    'invoice' => [
        'default_terms' => env('INVOICE_DEFAULT_TERMS', ''),
    ],
    'inventory' => [
        'default_safe_stock' => env('INVENTORY_DEFAULT_SAFE_STOCK', 0),
    ],
];
