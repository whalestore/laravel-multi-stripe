<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response;
use Whalestore\LaravelMultiStripe\Managers\StripeAccountManager;

class MultiStripeWebhookController extends Controller
{
    public function __construct(
        protected StripeAccountManager $manager,
    ) {
    }

    /**
     * 处理来自 Stripe 的 Webhook 请求（多账户、多环境）。
     */
    public function handle(Request $request): Response
    {
        $accountId = (string) $request->route('account');
        $environmentPlaceholder = config('multi-stripe.webhook.environment_placeholder', 'environment');
        /** @var mixed $envFromRoute */
        $envFromRoute = $request->route($environmentPlaceholder);
        $environment = is_string($envFromRoute) && $envFromRoute !== ''
            ? $envFromRoute
            : config('multi-stripe.default_environment', 'test');

        $config = $this->manager->get($accountId, $environment);

        $webhookSecret = $config->webhookSecret();

        if (! is_string($webhookSecret) || $webhookSecret === '') {
            return new Response('Webhook secret not configured.', Response::HTTP_BAD_REQUEST);
        }

        // 将当前账户/环境对应的 webhook secret 写入 Cashier 配置，
        // 让 Cashier 自己完成事件解析与签名验证。
        config(['cashier.webhook.secret' => $webhookSecret]);

        // 将事件交给 Cashier 原有的 WebhookController 处理
        /** @var WebhookController $cashierController */
        $cashierController = app(WebhookController::class);

        // Cashier 的 WebhookController 期望从 Request 中解析事件，这里简单复用当前 Request。
        // 如需更精细的控制，可以考虑扩展 Cashier 的控制器。
        return $cashierController($request);
    }
}


