<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Whalestore\LaravelMultiStripe\Contracts\StripeAccountResolver;
use Whalestore\LaravelMultiStripe\Managers\StripeAccountManager;
use Whalestore\LaravelMultiStripe\Support\StripeAccountConfig;
use Whalestore\LaravelMultiStripe\Support\StripeContext;

class SetCurrentStripeContext
{
    public function __construct(
        protected Container $container,
        protected StripeAccountResolver $resolver,
        protected StripeAccountManager $manager,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $billable = $this->resolveBillableFromRequest($request);

        $resolved = $this->resolver->resolve($billable);

        if ($resolved !== null) {
            $config = $this->manager->get($resolved['account'], $resolved['environment']);

            // 将当前账户配置与上下文绑定到容器，后续可通过依赖注入获取
            $this->container->instance(StripeAccountConfig::class, $config);
            $this->container->instance(StripeContext::class, new StripeContext($config));

            // 重要：同步更新 Cashier 所依赖的全局配置，确保即便直接调用 Cashier 内部逻辑也能使用正确私钥。
            config(['cashier.secret' => $config->secret()]);
        }

        return $next($request);
    }

    protected function resolveBillableFromRequest(Request $request): ?Model
    {
        // 如果请求已经被 TokenAuth 等中间件验证，则直接使用注入的 user 实体。
        if (isset($request->user) && $request->user instanceof Model) {
            return $request->user;
        }

        return null;
    }
}


