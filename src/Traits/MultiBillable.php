<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Traits;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;
use Laravel\Cashier\SubscriptionBuilder;
use Whalestore\LaravelMultiStripe\Services\MultiStripeClientFactory;
use Whalestore\LaravelMultiStripe\Support\StripeAccountConfig;
use Whalestore\LaravelMultiStripe\Support\StripeContext;

trait MultiBillable
{
    use Billable;

    /**
     * 获取当前模型对应的 StripeClient（根据账户 + 环境）。
     */
    public function stripeClient(): \Stripe\StripeClient
    {
        /** @var MultiStripeClientFactory $factory */
        $factory = app(MultiStripeClientFactory::class);

        /** @var Model $this */
        return $factory->forBillable($this);
    }

    /**
     * 多账户感知的订阅创建入口。
     *
     * 在调用父类的 newSubscription 之前，根据当前 billable 解析出账户+环境，
     * 并暂时覆盖 Cashier 的 secret 配置。
     */
    public function newSubscription(string $subscription, string|string[] $prices): SubscriptionBuilder
    {
        [$config, $previousSecret] = $this->withStripeConfigForCurrentBillable();

        try {
            /** @var SubscriptionBuilder $builder */
            $builder = parent::newSubscription($subscription, $prices);

            // 将 StripeAccountConfig 注入到 SubscriptionBuilder，便于调用方在需要时访问。
            $builder->stripeOptions = array_merge(
                $builder->stripeOptions ?? [],
                [
                    'account_id' => $config->accountId(),
                    'environment' => $config->environment(),
                ]
            );

            return $builder;
        } finally {
            // 恢复原始配置，避免影响其他逻辑
            if ($previousSecret !== null) {
                config(['cashier.secret' => $previousSecret]);
            }
        }
    }

    /**
     * 多账户感知的即时扣款。
     *
     * 在调用父类 charge 前切换到当前账户对应的 secret。
     *
     * @param  int  $amount
     * @param  array<string, mixed>  $options
     */
    public function charge($amount, $paymentMethod = null, array $options = [])
    {
        [, $previousSecret] = $this->withStripeConfigForCurrentBillable();

        try {
            return parent::charge($amount, $paymentMethod, $options);
        } finally {
            if ($previousSecret !== null) {
                config(['cashier.secret' => $previousSecret]);
            }
        }
    }

    /**
     * 为当前 billable 临时设置 Cashier 使用的 Stripe secret，并返回账户配置和之前的 secret。
     *
     * @return array{0: StripeAccountConfig, 1: string|null}
     */
    protected function withStripeConfigForCurrentBillable(): array
    {
        /** @var Model $this */
        $billable = $this;

        /** @var MultiStripeClientFactory $factory */
        $factory = app(MultiStripeClientFactory::class);

        /** @var \Whalestore\LaravelMultiStripe\Contracts\StripeAccountResolver $resolver */
        $resolver = app(\Whalestore\LaravelMultiStripe\Contracts\StripeAccountResolver::class);

        /** @var \Whalestore\LaravelMultiStripe\Managers\StripeAccountManager $manager */
        $manager = app(\Whalestore\LaravelMultiStripe\Managers\StripeAccountManager::class);

        $resolved = $resolver->resolve($billable);

        if ($resolved === null) {
            $environment = $manager->defaultEnvironment();
            $defaultAccount = $manager->defaultAccountId();

            if ($defaultAccount === null) {
                throw new \RuntimeException('No default Stripe account configured for MultiBillable.');
            }

            $config = $manager->get($defaultAccount, $environment);
        } else {
            $config = $manager->get($resolved['account'], $resolved['environment']);
        }

        // 记录之前的 secret 并切换到当前账户 secret
        $previousSecret = config('cashier.secret');
        config(['cashier.secret' => $config->secret()]);

        // 同时将上下文绑定到容器，便于其它依赖使用
        app()->instance(StripeAccountConfig::class, $config);
        app()->instance(StripeContext::class, new StripeContext($config));

        return [$config, is_string($previousSecret) ? $previousSecret : null];
    }
}


