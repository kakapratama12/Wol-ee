<?php

return [
    'plans' => [
        'free' => [
            'daily_ai_quota' => (int) env('AI_PLAN_FREE_DAILY_QUOTA', 25),
            'provider' => env('AI_PLAN_FREE_PROVIDER', 'groq'),
        ],
        'pro' => [
            'daily_ai_quota' => (int) env('AI_PLAN_PRO_DAILY_QUOTA', 150),
            'provider' => env('AI_PLAN_PRO_PROVIDER', 'openrouter'),
        ],
        'business' => [
            'daily_ai_quota' => (int) env('AI_PLAN_BUSINESS_DAILY_QUOTA', 150),
            'provider' => env('AI_PLAN_BUSINESS_PROVIDER', 'openrouter'),
        ],
    ],
    'providers' => [
        'groq' => [
            'label' => 'Groq',
            'rpm_limit' => (int) env('AI_PROVIDER_GROQ_RPM_LIMIT', 30),
            'rpd_limit' => (int) env('AI_PROVIDER_GROQ_RPD_LIMIT', 14400),
        ],
        'openrouter' => [
            'label' => 'OpenRouter / DeepSeek',
            'rpm_limit' => (int) env('AI_PROVIDER_OPENROUTER_RPM_LIMIT', 120),
            'rpd_limit' => (int) env('AI_PROVIDER_OPENROUTER_RPD_LIMIT', 100000),
        ],
    ],
];
