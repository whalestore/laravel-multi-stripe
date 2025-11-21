<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Stripe\StripeClient;
use Whalestore\LaravelMultiStripe\Services\MultiStripeClientFactory;

/**
 * @method static StripeClient for(string $accountId, ?string $environment = null)
 * @method static StripeClient forBillable(Model $billable)
 */
class MultiStripe extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'multi-stripe.factory';
    }

    public static function client(string $accountId, ?string $environment = null): StripeClient
    {
        /** @var MultiStripeClientFactory $factory */
        $factory = static::getFacadeRoot();

        return $factory->for($accountId, $environment);
    }
}


