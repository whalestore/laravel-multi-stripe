<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use Stripe\StripeClient;
use Whalestore\LaravelMultiStripe\Contracts\StripeAccountResolver;
use Whalestore\LaravelMultiStripe\Managers\StripeAccountManager;
use Whalestore\LaravelMultiStripe\Services\MultiStripeClientFactory;

class MultiStripeClientFactoryTest extends TestCase
{
    public function test_creates_client_for_given_account_and_environment(): void
    {
        $manager = new StripeAccountManager([
            'default_environment' => 'test',
            'accounts' => [
                'us' => [
                    'test' => ['secret' => 'sk_test_us'],
                ],
            ],
        ]);

        $resolver = $this->createMock(StripeAccountResolver::class);

        $factory = new MultiStripeClientFactory($manager, $resolver);

        $client = $factory->for('us', 'test');

        $this->assertInstanceOf(StripeClient::class, $client);
    }

    public function test_for_billable_uses_resolver(): void
    {
        $manager = new StripeAccountManager([
            'default_environment' => 'test',
            'accounts' => [
                'us' => [
                    'test' => ['secret' => 'sk_test_us'],
                ],
            ],
        ]);

        $resolver = $this->createMock(StripeAccountResolver::class);
        $resolver->method('resolve')->willReturn([
            'account' => 'us',
            'environment' => 'test',
        ]);

        $factory = new MultiStripeClientFactory($manager, $resolver);

        $billable = $this->createMock(Model::class);

        $client = $factory->forBillable($billable);

        $this->assertInstanceOf(StripeClient::class, $client);
    }
}


