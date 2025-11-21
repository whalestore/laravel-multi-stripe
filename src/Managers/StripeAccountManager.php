<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Managers;

use InvalidArgumentException;
use Whalestore\LaravelMultiStripe\Support\StripeAccountConfig;

class StripeAccountManager
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @var array<string, StripeAccountConfig>
     */
    private array $cache = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function defaultEnvironment(): string
    {
        $environment = $this->config['default_environment'] ?? 'test';

        return is_string($environment) ? $environment : 'test';
    }

    /**
     * 返回所有已配置的逻辑账户 ID 列表。
     *
     * @return string[]
     */
    public function accountIds(): array
    {
        $accounts = $this->config['accounts'] ?? [];

        if (! is_array($accounts)) {
            return [];
        }

        return array_keys($accounts);
    }

    /**
     * 返回默认逻辑账户 ID：
     * - 优先使用配置项 default_account
     * - 否则取第一个已配置的账户
     */
    public function defaultAccountId(): ?string
    {
        $default = $this->config['default_account'] ?? null;

        if (is_string($default) && $default !== '') {
            return $default;
        }

        $ids = $this->accountIds();

        return $ids[0] ?? null;
    }

    /**
     * 获取指定账户与环境的配置。
     */
    public function get(string $accountId, ?string $environment = null): StripeAccountConfig
    {
        $environment ??= $this->defaultEnvironment();

        $cacheKey = $accountId . ':' . $environment;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $accounts = $this->config['accounts'] ?? [];

        if (! isset($accounts[$accountId]) || ! is_array($accounts[$accountId])) {
            throw new InvalidArgumentException(sprintf('Stripe account [%s] is not configured.', $accountId));
        }

        $accountConfig = $accounts[$accountId];

        if (! isset($accountConfig[$environment]) || ! is_array($accountConfig[$environment])) {
            throw new InvalidArgumentException(sprintf(
                'Environment [%s] for Stripe account [%s] is not configured.',
                $environment,
                $accountId
            ));
        }

        $envConfig = $accountConfig[$environment];

        $config = StripeAccountConfig::fromArray($accountId, $environment, $envConfig);

        return $this->cache[$cacheKey] = $config;
    }
}


