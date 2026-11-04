<?php

return [
    // Payment providers offered at checkout. "sandbox" is a test gateway that
    // simulates a hosted payment page; never enable it in production.
    'providers' => array_filter(explode(',', env('BILLING_PROVIDERS', env('APP_ENV') === 'production' ? 'manual' : 'manual,sandbox'))),

    'sandbox' => [
        'secret' => env('BILLING_SANDBOX_SECRET', env('APP_KEY')),
        // Callbacks older than this are rejected (replay protection).
        'max_age_seconds' => 600,
    ],

    'invoice_due_days' => 7,
    'default_grace_days' => 7,
    'default_trial_plan' => 'basic',
];
