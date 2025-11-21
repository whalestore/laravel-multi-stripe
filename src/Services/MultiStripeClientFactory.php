<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Services;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Stripe\StripeClient;
use Whalestore\LaravelMultiStripe\Contracts\StripeAccountResolver;
use Whalestore\LaravelMultiStripe\Managers\StripeAccountManager;
use Whalestore\LaravelMultiStripe\Support\StripeAccountConfig;

class MultiStripeClientFactory
{
    /**
     * @var array<string, StripeClient>
     */
    private array $clients = [];

    public function __construct(
        protected StripeAccountManager $manager,
        protected StripeAccountResolver $resolver,
    ) {
    }

    public function for(string $accountId, ?string $environment = null): StripeClient
    {
        $config = $this->manager->get($accountId, $environment);

        return $this->forConfig($config);
    }

    public function forConfig(StripeAccountConfig $config): StripeClient
    {
        $key = $config->accountId() . ':' . $config->environment();

        if (isset($this->clients[$key])) {
            return $this->clients[$key];
        }

        return $this->clients[$key] = new StripeClient($config->secret());
    }

    public function forBillable(Model $billable): StripeClient
    {
        $resolved = $this->resolver->resolve($billable);

        if ($resolved === null) {
            // 无法解析时，尝试使用默认环境与第一个配置的逻辑账户
            $environment = $this->manager->defaultEnvironment();

            $defaultAccount = $this->manager->defaultAccountId();

            if ($defaultAccount === null) {
                throw new InvalidArgumentException('No default Stripe account is configured.');
            }

            return $this->for($defaultAccount, $environment);
        }

        return $this->for($resolved['account'], $resolved['environment']);
    }
}


