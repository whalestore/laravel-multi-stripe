<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Support;

final class StripeAccountConfig
{
    public function __construct(
        private readonly string $accountId,
        private readonly string $environment,
        private readonly string $secret,
        private readonly ?string $publishableKey = null,
        private readonly ?string $webhookSecret = null,
        private readonly ?string $currency = null,
        private readonly array $extra = [],
    ) {
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function secret(): string
    {
        return $this->secret;
    }

    public function publishableKey(): ?string
    {
        return $this->publishableKey;
    }

    public function webhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    public function currency(): ?string
    {
        return $this->currency;
    }

    /**
    * 额外自定义字段。
    *
    * @return array<string, mixed>
    */
    public function extra(): array
    {
        return $this->extra;
    }

    /**
     * 从配置数组构建实例。
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(string $accountId, string $environment, array $config): self
    {
        return new self(
            $accountId,
            $environment,
            (string) ($config['secret'] ?? ''),
            isset($config['publishable_key']) ? (string) $config['publishable_key'] : null,
            isset($config['webhook_secret']) ? (string) $config['webhook_secret'] : null,
            isset($config['currency']) ? (string) $config['currency'] : null,
            $config['extra'] ?? []
        );
    }
}


