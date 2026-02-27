<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response;
use Whalestore\LaravelMultiStripe\Managers\StripeAccountManager;
use Illuminate\Support\Facades\Log;

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
            : $this->resolveEnvironment($accountId, $request);

        Log::info('[PAY_FLOW] MultiStripeWebhook: Received webhook', [
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'account_parameter' => $accountId,
            'env_parameter' => $environment,
            'signature' => $request->header('Stripe-Signature') ? 'present' : 'missing',
        ]);

        $config = $this->manager->get($accountId, $environment);

        Log::info('[PAY_FLOW] MultiStripeWebhook: Resolved config', [
            'account' => $config->accountId(),
            'environment' => $config->environment(),
        ]);

        $webhookSecret = $config->webhookSecret();

        if (! is_string($webhookSecret) || $webhookSecret === '') {
            return new Response('Webhook secret not configured.', Response::HTTP_BAD_REQUEST);
        }

        // 将当前账户/环境对应的 webhook secret 写入 Cashier 配置，
        // 让 Cashier 自己完成事件解析与签名验证。
        config(['cashier.webhook.secret' => $webhookSecret]);
        config(['cashier.secret' => $config->secret()]);

        // 将事件交给 Cashier 原有的 WebhookController 处理
        /** @var WebhookController $cashierController */
        $cashierController = app(WebhookController::class);

        // Cashier 的 WebhookController 期望从 Request 中解析事件，这里简单复用当前 Request。
        // 如需更精细的控制，可以考虑扩展 Cashier 的控制器。
        return $cashierController->handleWebhook($request);
    }

    /**
     * 根据上下文（域名、账户 ID 等）尝试解析环境。
     */
    protected function resolveEnvironment(string $accountId, Request $request): string
    {
        // 1. 尝试从域名解析 (RegionService)
        try {
            $regionService = app(\App\Services\RegionService::class);
            if ($regionService && $regionService->getStripeAccount() === $accountId) {
                return $regionService->getStripeEnvironment();
            }
        } catch (\Throwable $e) {
            // RegionService 不可用或未注册
        }

        // 2. 尝试从 config/regions.php 静态匹配（如果域名不匹配也可以根据账户 ID 猜）
        $regions = config('regions.regions', []);
        foreach ($regions as $region) {
            if (($region['stripe_account'] ?? null) === $accountId) {
                return $region['stripe_environment'] ?? 'test';
            }
        }

        // 3. 最后退回到全局默认值
        return config('multi-stripe.default_environment', 'test');
    }
}


