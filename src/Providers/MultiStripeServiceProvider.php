<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use Whalestore\LaravelMultiStripe\Contracts\StripeAccountResolver;
use Whalestore\LaravelMultiStripe\Managers\StripeAccountManager;
use Whalestore\LaravelMultiStripe\Resolvers\ConfigStripeAccountResolver;
use Whalestore\LaravelMultiStripe\Services\MultiStripeClientFactory;
use Whalestore\LaravelMultiStripe\Http\Middleware\SetCurrentStripeContext;
use Whalestore\LaravelMultiStripe\Console\Commands\MultiStripeWebhookSyncCommand;

class MultiStripeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/multi-stripe.php',
            'multi-stripe'
        );

        $this->app->singleton(StripeAccountManager::class, function (Container $app): StripeAccountManager {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('multi-stripe', []);

            return new StripeAccountManager($config);
        });

        $this->app->singleton(StripeAccountResolver::class, function (Container $app): StripeAccountResolver {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('multi-stripe.resolver', []);
            $resolverClass = $config['class'] ?? ConfigStripeAccountResolver::class;
            $options = $config['options'] ?? [];

            return new $resolverClass($app['request'], $app->make(StripeAccountManager::class), $options);
        });

        $this->app->singleton(MultiStripeClientFactory::class, function (Container $app): MultiStripeClientFactory {
            return new MultiStripeClientFactory(
                $app->make(StripeAccountManager::class),
                $app->make(StripeAccountResolver::class)
            );
        });

        // 绑定 facade 访问的服务名
        $this->app->alias(MultiStripeClientFactory::class, 'multi-stripe.factory');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/multi-stripe.php' => config_path('multi-stripe.php'),
        ], 'config');

        $this->registerMiddleware();
        $this->registerRoutes();
        $this->registerCommands();
    }

    /**
     * Register package middleware aliases.
     */
    protected function registerMiddleware(): void
    {
        if (! $this->app->resolved('router')) {
            return;
        }

        /** @var Router $router */
        $router = $this->app['router'];

        $router->aliasMiddleware('multi-stripe.context', SetCurrentStripeContext::class);
    }

    /**
     * Register package console commands.
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            MultiStripeWebhookSyncCommand::class,
        ]);
    }

    /**
     * Register package routes.
     */
    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        /** @var array<string, mixed> $config */
        $config = $this->app['config']->get('multi-stripe.webhook', []);

        $path = $config['path'] ?? 'stripe/{account}/webhook';
        $middleware = $config['middleware'] ?? ['api'];

        /** @var Router $router */
        $router = $this->app['router'];

        $router->group([
            'middleware' => $middleware,
        ], function () use ($router, $path): void {
            $router->post($path, [
                'uses' => '\\Whalestore\\LaravelMultiStripe\\Http\\Controllers\\MultiStripeWebhookController@handle',
                'as' => 'multi-stripe.webhook',
            ]);
        });
    }
}


