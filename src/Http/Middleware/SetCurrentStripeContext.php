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
        }

        return $next($request);
    }

    protected function resolveBillableFromRequest(Request $request): ?Model
    {
        // 预留钩子：用户可以在自定义解析器中直接根据请求解析，无需 billable。
        // 这里默认返回 null，后续可通过扩展支持根据认证用户等自动推断。
        return null;
    }
}


