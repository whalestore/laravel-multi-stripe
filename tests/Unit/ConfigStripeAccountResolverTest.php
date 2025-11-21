<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Whalestore\LaravelMultiStripe\Managers\StripeAccountManager;
use Whalestore\LaravelMultiStripe\Resolvers\ConfigStripeAccountResolver;

class ConfigStripeAccountResolverTest extends TestCase
{
    public function test_resolves_from_query_parameters(): void
    {
        $request = Request::create('/test', 'GET', [
            'stripe_account' => 'us',
            'stripe_env' => 'live',
        ]);

        $manager = new StripeAccountManager([
            'default_environment' => 'test',
            'default_account' => 'eu',
        ]);

        $resolver = new ConfigStripeAccountResolver($request, $manager, []);

        $resolved = $resolver->resolve();

        $this->assertNotNull($resolved);
        $this->assertSame('us', $resolved['account']);
        $this->assertSame('live', $resolved['environment']);
    }

    public function test_falls_back_to_default_account_and_environment(): void
    {
        $request = Request::create('/test', 'GET');

        $manager = new StripeAccountManager([
            'default_environment' => 'test',
            'default_account' => 'us',
        ]);

        $resolver = new ConfigStripeAccountResolver($request, $manager, []);

        $resolved = $resolver->resolve();

        $this->assertNotNull($resolved);
        $this->assertSame('us', $resolved['account']);
        $this->assertSame('test', $resolved['environment']);
    }
}


