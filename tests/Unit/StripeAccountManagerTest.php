<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Whalestore\LaravelMultiStripe\Managers\StripeAccountManager;

class StripeAccountManagerTest extends TestCase
{
    public function test_default_environment_falls_back_to_test(): void
    {
        $manager = new StripeAccountManager([]);

        $this->assertSame('test', $manager->defaultEnvironment());
    }

    public function test_can_get_account_config_for_environment(): void
    {
        $config = [
            'default_environment' => 'test',
            'accounts' => [
                'us' => [
                    'test' => [
                        'secret' => 'sk_test_us',
                    ],
                ],
            ],
        ];

        $manager = new StripeAccountManager($config);

        $accountConfig = $manager->get('us', 'test');

        $this->assertSame('us', $accountConfig->accountId());
        $this->assertSame('test', $accountConfig->environment());
        $this->assertSame('sk_test_us', $accountConfig->secret());
    }

    public function test_default_account_id_uses_configured_default(): void
    {
        $config = [
            'default_account' => 'eu',
            'accounts' => [
                'us' => [],
                'eu' => [],
            ],
        ];

        $manager = new StripeAccountManager($config);

        $this->assertSame('eu', $manager->defaultAccountId());
    }

    public function test_default_account_id_uses_first_when_not_configured(): void
    {
        $config = [
            'accounts' => [
                'us' => [],
                'eu' => [],
            ],
        ];

        $manager = new StripeAccountManager($config);

        $this->assertSame('us', $manager->defaultAccountId());
    }
}


