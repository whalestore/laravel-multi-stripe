<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Whalestore\LaravelMultiStripe\Contracts\StripeAccountResolver;
use Whalestore\LaravelMultiStripe\Managers\StripeAccountManager;

class ConfigStripeAccountResolver implements StripeAccountResolver
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        protected Request $request,
        protected StripeAccountManager $manager,
        protected array $options = [],
    ) {
    }

    public function resolve(?Model $billable = null): ?array
    {
        // 1. 优先从 billable（如用户模型）解析
        if ($billable !== null) {
            $fromBillable = $this->resolveFromBillable($billable);

            if ($fromBillable !== null) {
                return $fromBillable;
            }
        }

        // 2. 从路由参数解析
        $fromRoute = $this->resolveFromRoute();
        if ($fromRoute !== null) {
            return $fromRoute;
        }

        // 3. 从 query / header 解析
        $fromRequest = $this->resolveFromRequest();
        if ($fromRequest !== null) {
            return $fromRequest;
        }

        // 4. 回退到默认环境与默认逻辑账户（如果存在）
        $defaultEnvironment = $this->manager->defaultEnvironment();
        $defaultAccount = $this->resolveDefaultAccount();

        if ($defaultAccount !== null) {
            return [
                'account' => $defaultAccount,
                'environment' => $defaultEnvironment,
            ];
        }

        return null;
    }

    protected function resolveFromBillable(Model $billable): ?array
    {
        $accountField = $this->options['billable_account_field'] ?? 'stripe_account_id';
        $environmentField = $this->options['billable_environment_field'] ?? 'stripe_env';

        /** @var mixed $account */
        $account = $billable->getAttribute($accountField);
        /** @var mixed $environment */
        $environment = $billable->getAttribute($environmentField);

        if (! is_string($account) || $account === '') {
            return null;
        }

        $environment = is_string($environment) && $environment !== ''
            ? $environment
            : $this->manager->defaultEnvironment();

        return [
            'account' => $account,
            'environment' => $environment,
        ];
    }

    protected function resolveFromRoute(): ?array
    {
        $accountKey = $this->options['account_route_key'] ?? 'stripe_account';
        $environmentKey = $this->options['environment_route_key'] ?? 'stripe_env';

        /** @var mixed $account */
        $account = $this->request->route($accountKey);
        /** @var mixed $environment */
        $environment = $this->request->route($environmentKey);

        if (! is_string($account) || $account === '') {
            return null;
        }

        $environment = is_string($environment) && $environment !== ''
            ? $environment
            : $this->manager->defaultEnvironment();

        return [
            'account' => $account,
            'environment' => $environment,
        ];
    }

    protected function resolveFromRequest(): ?array
    {
        $accountParam = $this->options['account_param'] ?? 'stripe_account';
        $environmentParam = $this->options['environment_param'] ?? 'stripe_env';

        $accountHeader = $this->options['account_header'] ?? 'X-Stripe-Account';
        $environmentHeader = $this->options['environment_header'] ?? 'X-Stripe-Env';

        /** @var mixed $account */
        $account = $this->request->query($accountParam, $this->request->header($accountHeader));

        if (! is_string($account) || $account === '') {
            return null;
        }

        /** @var mixed $environment */
        $environment = $this->request->query($environmentParam, $this->request->header($environmentHeader));

        $environment = is_string($environment) && $environment !== ''
            ? $environment
            : $this->manager->defaultEnvironment();

        return [
            'account' => $account,
            'environment' => $environment,
        ];
    }

    protected function resolveDefaultAccount(): ?string
    {
        // 1. 若 options 中显式配置了默认账户列表，则优先使用
        $defaultAccounts = $this->options['default_accounts'] ?? null;

        if (is_array($defaultAccounts) && ! empty($defaultAccounts)) {
            $first = $defaultAccounts[0];

            return is_string($first) && $first !== '' ? $first : null;
        }

        // 2. 否则退回到管理器中配置的 default_account / 第一个账户
        return $this->manager->defaultAccountId();
    }
}


