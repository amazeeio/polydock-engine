<?php

declare(strict_types=1);

namespace App\Providers;

use App\Queue\Failed\SafeDatabaseUuidFailedJobProvider;
use App\Services\EmailBlockerService;
use App\Services\PolydockAppClassDiscovery;
use Aws\DynamoDb\DynamoDbClient;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\Failed\DynamoDbFailedJobProvider;
use Illuminate\Queue\Failed\FileFailedJobProvider;
use Illuminate\Queue\Failed\NullFailedJobProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
        $this->app->singleton(EmailBlockerService::class);

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

        Event::listen(
            CommandStarting::class,
            static function (CommandStarting $event) {
                $command = $event->command ?? 'artisan';

                // Get raw CLI argv if available
                $argv = $_SERVER['argv'] ?? [];

                $redactedArgv = [];
                $redactNext = false;
                $exactSensitiveKeys = ['p']; // Narrowed to prevent false positives like -t or -k
                $substringSensitiveKeys = ['password', 'pass', 'secret', 'token', 'key', 'auth', 'apikey'];

                $commandInstance = null;
                try {
                    $kernel = app(Kernel::class);
                    $property = (new \ReflectionClass($kernel))->getProperty('artisan');
                    $property->setAccessible(true);
                    $artisan = $property->getValue($kernel);
                    if ($artisan) {
                        $commandInstance = $artisan->find($command);
                    }
                } catch (\Throwable $e) {
                    $commandInstance = null;
                }

                if ($commandInstance === null) {
                    static $allCommandsFallback = null;
                    if ($allCommandsFallback === null) {
                        try {
                            $allCommandsFallback = Artisan::all();
                        } catch (\Throwable $e) {
                            $allCommandsFallback = [];
                        }
                    }
                    $commandInstance = $allCommandsFallback[$command] ?? null;
                }

                $explicitSensitiveKeys = [];
                if ($commandInstance && method_exists($commandInstance, 'sensitiveInputs')) {
                    $explicitSensitiveKeys = array_map('strtolower', $commandInstance->sensitiveInputs());
                }

                $definedArguments = [];
                if ($commandInstance) {
                    $definedArguments = array_values($commandInstance->getDefinition()->getArguments());
                }

                $positionalIndex = 0;
                $passedCommandName = false;

                $isKeySensitive = static function (string $key) use ($exactSensitiveKeys, $substringSensitiveKeys, $explicitSensitiveKeys): bool {
                    $keyLower = strtolower($key);
                    if (in_array($keyLower, $exactSensitiveKeys, true)) {
                        return true;
                    }
                    if (in_array($keyLower, $explicitSensitiveKeys, true)) {
                        return true;
                    }
                    foreach ($substringSensitiveKeys as $substring) {
                        if (str_contains($keyLower, $substring)) {
                            return true;
                        }
                    }

                    return false;
                };

                $optionExpectingValue = null;

                foreach ($argv as $index => $arg) {
                    if ($index === 0) {
                        $redactedArgv[] = $arg;

                        continue;
                    }

                    if ($redactNext) {
                        if (! str_starts_with($arg, '-')) {
                            $redactedArgv[] = '[REDACTED]';
                            $redactNext = false;
                            $optionExpectingValue = null;

                            continue;
                        }
                        $redactNext = false;
                        $optionExpectingValue = null;
                    } elseif ($optionExpectingValue) {
                        if (! str_starts_with($arg, '-')) {
                            $redactedArgv[] = $arg;
                            $optionExpectingValue = null;

                            continue;
                        }
                        $optionExpectingValue = null;
                    }

                    if (str_starts_with($arg, '-')) {
                        if (str_contains($arg, '=')) {
                            [$key, $value] = explode('=', $arg, 2);
                            $optionName = ltrim($key, '-');

                            $option = null;
                            if ($commandInstance) {
                                $definition = $commandInstance->getDefinition();
                                if ($definition->hasOption($optionName)) {
                                    $option = $definition->getOption($optionName);
                                } elseif ($definition->hasShortcut($optionName)) {
                                    $option = $definition->getOptionForShortcut($optionName);
                                }
                            }

                            $optionNameToCheck = $option ? $option->getName() : $optionName;
                            if ($isKeySensitive($optionNameToCheck)) {
                                $redactedArgv[] = $key.'=[REDACTED]';
                            } else {
                                $redactedArgv[] = $arg;
                            }
                        } else {
                            $optionName = ltrim($arg, '-');

                            $option = null;
                            if ($commandInstance) {
                                $definition = $commandInstance->getDefinition();
                                if ($definition->hasOption($optionName)) {
                                    $option = $definition->getOption($optionName);
                                } elseif ($definition->hasShortcut($optionName)) {
                                    $option = $definition->getOptionForShortcut($optionName);
                                }
                            }

                            $optionNameToCheck = $option ? $option->getName() : $optionName;
                            if ($isKeySensitive($optionNameToCheck)) {
                                $redactNext = true;
                            } else {
                                if ($option && $option->acceptValue()) {
                                    $optionExpectingValue = $option;
                                }
                            }
                            $redactedArgv[] = $arg;
                        }
                    } else {
                        if (strtolower($arg) === strtolower($command)) {
                            $passedCommandName = true;
                            $redactedArgv[] = $arg;
                        } elseif (! $passedCommandName) {
                            $redactedArgv[] = $arg;
                        } else {
                            $definedArg = $definedArguments[$positionalIndex] ?? null;
                            $positionalIndex++;

                            if ($definedArg && $isKeySensitive($definedArg->getName())) {
                                $redactedArgv[] = '[REDACTED]';
                            } else {
                                $redactedArgv[] = $arg;
                            }
                        }
                    }
                }

                $commandLine = implode(' ', $redactedArgv);

                Log::shareContext([
                    'artisan_command' => $command,
                    'artisan_argv' => $commandLine,
                ]);
            }
        );
    }
}
