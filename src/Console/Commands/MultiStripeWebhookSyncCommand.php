<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Whalestore\LaravelMultiStripe\Managers\StripeAccountManager;

class MultiStripeWebhookSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *  php artisan multi-stripe:webhook:sync us --env=test
     */
    protected $signature = 'multi-stripe:webhook:sync 
                            {account : The logical Stripe account ID configured in multi-stripe.accounts}
                            {--env= : The Stripe environment (test or live). Defaults to multi-stripe.default_environment}';

    /**
     * The console command description.
     */
    protected $description = 'Create or sync a Stripe webhook endpoint for a specific logical account and environment.';

    public function handle(StripeAccountManager $accountManager): int
    {
        $accountId = (string) $this->argument('account');
        $environment = (string) ($this->option('env') ?: $accountManager->defaultEnvironment());

        $this->info('------------------------------------------------------------');
        $this->info('Multi Stripe Webhook Sync');
        $this->info('------------------------------------------------------------');
        $this->line("Account     : <info>{$accountId}</info>");
        $this->line("Environment : <info>{$environment}</info>");

        try {
            $accountConfig = $accountManager->get($accountId, $environment);
        } catch (\Throwable $e) {
            $this->error("Failed to resolve Stripe account configuration: {$e->getMessage()}");

            return self::FAILURE;
        }

        $path = (string) config('multi-stripe.webhook.path', 'stripe/{account}/webhook');
        $envPlaceholder = (string) config('multi-stripe.webhook.environment_placeholder', 'environment');

        // Build the relative path by replacing placeholders.
        $relativePath = str_replace(
            ['{account}', '{' . $envPlaceholder . '}'],
            [$accountId, $environment],
            $path
        );

        $baseUrl = (string) config('app.url');
        $baseUrl = rtrim($baseUrl, '/');
        $relativePath = '/' . ltrim($relativePath, '/');
        $url = $baseUrl . $relativePath;

        $this->line('');
        $this->line('Webhook endpoint URL that will be used:');
        $this->line("  <comment>{$url}</comment>");

        $client = new StripeClient($accountConfig->secret());

        // Determine which events to subscribe to. We reuse Cashier"s configuration when possible.
        $events = config('cashier.webhook.events', ['*']);

        $this->line('');
        $this->line('Creating webhook endpoint on Stripe...');

        try {
            /** @var array<string, mixed> $params */
            $params = [
                'url' => $url,
                'enabled_events' => $events,
            ];

            $endpoint = $client->webhookEndpoints->create($params);
        } catch (ApiErrorException $exception) {
            $this->error('Stripe API error while creating webhook endpoint: ' . $exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error('Unexpected error while creating webhook endpoint: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->info('Webhook endpoint created successfully on Stripe.');
        $this->line("Endpoint ID: <info>{$endpoint->id}</info>");

        // For security reasons, Stripe only returns the signing secret on creation.
        $signingSecret = $endpoint->secret ?? null;

        if (is_string($signingSecret) && $signingSecret !== '') {
            $this->line('');
            $this->info('Signing secret for this endpoint:');
            $this->line("  <comment>{$signingSecret}</comment>");
            $this->line('');
            $this->line('Please copy this value into your configuration, for example:');
            $this->line("  <comment>config/multi-stripe.php</comment> under:");
            $this->line("  accounts[{$accountId}]['{$environment}']['webhook_secret']");
            $this->line('or into your .env file referenced by that configuration key.');
        } else {
            $this->line('');
            $this->warn('Stripe did not return a signing secret for this endpoint.');
            $this->line('If this endpoint already existed, Stripe will not send the secret again.');
            $this->line('Please retrieve the signing secret manually from your Stripe dashboard and configure it under:');
            $this->line("  accounts[{$accountId}]['{$environment}']['webhook_secret']");
        }

        $this->line('');
        $this->info('Done.');

        return self::SUCCESS;
    }
}


