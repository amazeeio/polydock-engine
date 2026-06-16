<?php

declare(strict_types=1);

namespace App\Providers;

use App\Queue\Failed\SafeDatabaseUuidFailedJobProvider;
use App\Services\PolydockAppClassDiscovery;
use Aws\DynamoDb\DynamoDbClient;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\Failed\DynamoDbFailedJobProvider;
use Illuminate\Queue\Failed\FileFailedJobProvider;
use Illuminate\Queue\Failed\NullFailedJobProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(PolydockAppClassDiscovery::class);

        Scramble::ignoreDefaultRoutes();

        // Force load QueueServiceProvider so that our queue.failer override is not overwritten by deferred loading
        $this->app->register(QueueServiceProvider::class);

        $this->app->singleton('queue.failer', function ($app) {
            $config = $app['config']['queue.failed'];

            if (isset($config['driver']) && $config['driver'] === 'database-uuids') {
                return new SafeDatabaseUuidFailedJobProvider(
                    $app['db'], $config['database'], $config['table']
                );
            }

            // Fallback for other drivers using Laravel's standard resolution logic:
            if (array_key_exists('driver', $config) &&
                (is_null($config['driver']) || $config['driver'] === 'null')) {
                return new NullFailedJobProvider;
            }

            if (isset($config['driver']) && $config['driver'] === 'file') {
                return new FileFailedJobProvider(
                    $config['path'] ?? $app->storagePath('framework/cache/failed-jobs.json'),
                    $config['limit'] ?? 100,
                    fn () => $app['cache']->store('file'),
                );
            }

            if (isset($config['driver']) && $config['driver'] === 'dynamodb') {
                $dynamoConfig = [
                    'region' => $config['region'],
                    'version' => 'latest',
                    'endpoint' => $config['endpoint'] ?? null,
                ];

                if (! empty($config['key']) && ! empty($config['secret'])) {
                    $dynamoConfig['credentials'] = Arr::only($config, ['key', 'secret']);

                    if (! empty($config['token'])) {
                        $dynamoConfig['credentials']['token'] = $config['token'];
                    }
                }

                return new DynamoDbFailedJobProvider(
                    new DynamoDbClient($dynamoConfig),
                    $app['config']['app.name'],
                    $config['table']
                );
            }

            if (isset($config['table'])) {
                return new DatabaseFailedJobProvider(
                    $app['db'], $config['database'] ?? null, $config['table']
                );
            }

            return new NullFailedJobProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewApiDocs', fn (?Authenticatable $user) => true);

        Scramble::configure()->expose(
            ui: '/api',
            document: '/api/openapi.json',
        )
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')->as('BearerAuth')
                );
            });

        Scramble::routes(fn (Route $route) => str_starts_with($route->uri(), 'api/'));
    }
}
