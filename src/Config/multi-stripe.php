<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Stripe Environment
    |--------------------------------------------------------------------------
    |
    | 全局默认 Stripe 环境，可在运行时通过解析器覆盖。
    | 一般为 "test" 或 "live"。
    |
    */

    'default_environment' => env('STRIPE_ENVIRONMENT', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Supported Environments
    |--------------------------------------------------------------------------
    |
    | 支持的环境列表，通常为 ["test", "live"]。
    |
    */

    'environments' => ['test', 'live'],

    /*
    |--------------------------------------------------------------------------
    | Default Logical Account
    |--------------------------------------------------------------------------
    |
    | 默认逻辑账户 ID，当无法从上下文解析具体账户时会退回到此账户。
    | 若未配置，则使用 accounts 配置中的第一个账户。
    |
    */

    'default_account' => env('STRIPE_DEFAULT_ACCOUNT'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Accounts
    |--------------------------------------------------------------------------
    |
    | 逻辑账户列表。每个逻辑账户下可以为不同环境（test/live）
    | 定义独立的 key、webhook secret 等信息。
    |
    */

    'accounts' => [
        // 示例：
        // 'us' => [
        //     'name' => 'US Main Account',
        //
        //     'test' => [
        //         'secret'          => env('STRIPE_US_TEST_SECRET'),
        //         'publishable_key' => env('STRIPE_US_TEST_KEY'),
        //         'webhook_secret'  => env('STRIPE_US_TEST_WEBHOOK_SECRET'),
        //         'currency'        => 'usd',
        //     ],
        //
        //     'live' => [
        //         'secret'          => env('STRIPE_US_LIVE_SECRET'),
        //         'publishable_key' => env('STRIPE_US_LIVE_KEY'),
        //         'webhook_secret'  => env('STRIPE_US_LIVE_WEBHOOK_SECRET'),
        //         'currency'        => 'usd',
        //     ],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account & Environment Resolver
    |--------------------------------------------------------------------------
    |
    | 默认账户解析器实现及其选项。你可以通过实现
    | Whalestore\LaravelMultiStripe\Contracts\StripeAccountResolver
    | 来自定义解析逻辑，并在这里替换 class。
    |
    */

    'resolver' => [
        'class' => \Whalestore\LaravelMultiStripe\Resolvers\ConfigStripeAccountResolver::class,

        'options' => [
            // query 参数名，例如 ?stripe_account=us
            'account_param' => 'stripe_account',
            'environment_param' => 'stripe_env',

            // header 名，例如 X-Stripe-Account: us, X-Stripe-Env: test
            'account_header' => 'X-Stripe-Account',
            'environment_header' => 'X-Stripe-Env',

            // 路由参数键名，例如 route('x', ['stripe_account' => 'us'])
            'account_route_key' => 'stripe_account',
            'environment_route_key' => 'stripe_env',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Webhook 路由相关配置。默认使用 stripe/{account}/webhook，
    | 你可以根据需要自定义 path，并增加 environment 维度。
    |
    */

    'webhook' => [
        // 路由路径模板，必须包含 {account} 占位符。
        'path' => 'stripe/{account}/webhook',

        // 可选：当你使用 environment 作为路由参数时的占位符名，
        // 如 stripe/{account}/{environment}/webhook。
        'environment_placeholder' => 'environment',

        // 应用到 webhook 路由上的中间件组
        'middleware' => ['api'],
    ],
];


