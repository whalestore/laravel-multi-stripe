<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Support;

final class StripeContext
{
    public function __construct(
        private readonly StripeAccountConfig $accountConfig,
    ) {
    }

    public function accountId(): string
    {
        return $this->accountConfig->accountId();
    }

    public function environment(): string
    {
        return $this->accountConfig->environment();
    }

    public function config(): StripeAccountConfig
    {
        return $this->accountConfig;
    }
}


