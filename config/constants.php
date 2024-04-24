<?php

return [
    "DOC_URL"         => env('DOC_URL'),
    "RAZORPAY_KEY"    => env('RAZORPAY_KEY'),
    "RAZORPAY_SECRET" => env('RAZORPAY_SECRET'),

    "E_RICKSHAW_FINES" => [
        0 => "3000",
        1 => "5000",
        2 => "10000",
        3 => "15000",
        4 => "25000",
    ],

    "ID_GENERATION_PARAMS" => [
        "APPLICATION"                => 1,
        "CHALLAN"                    => 2,
        "RECEIPT"                    => 3,
        "CASH_VERIFICATION_ID"       => 4
    ],

    "WHATSAPP_TOKEN"        => env("WHATSAPP_TOKEN", "xxx"),
    "WHATSAPP_NUMBER_ID"    => env("WHATSAPP_NUMBER_ID", "xxx"),
    "WHATSAPP_URL"          => env("WHATSAPP_URL", "xxx"),

];
