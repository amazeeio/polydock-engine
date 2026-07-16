<?php

declare(strict_types=1);

namespace App\Providers;

use App\Queue\Failed\SafeDatabaseUuidFailedJobProvider;
use App\Services\EmailBlockerService;
use App\Services\PolydockAppClassDiscovery;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Facades\Health;

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

        // Swap in the safe database-uuids failed-job provider. The override is
        // only installed when that driver is configured — any other driver
        // keeps Laravel's stock resolution instead of silently losing failed
        // job records.
        if (($this->app['config']['queue.failed.driver'] ?? null) === 'database-uuids') {
            // Force load QueueServiceProvider so that our queue.failer override is not overwritten by deferred loading
            $this->app->register(QueueServiceProvider::class);

            $this->app->singleton('queue.failer', function ($app) {
                $config = $app['config']['queue.failed'];

                return new SafeDatabaseUuidFailedJobProvider(
                    $app['db'], $config['database'], $config['table']
                );
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Health::checks([
            HorizonCheck::new(),
        ]);

        // Named rate limiters for the unauthenticated public API routes. Named
        // limiters key on the limiter name + IP, so each route has its own
        // counter — unlike anonymous `throttle:N,1`, whose signature is
        // sha1(domain|ip) and is therefore shared across every route per IP.
        // Trusted internal callers (e.g. MoaD) bypass the public throttles.
        $isTrusted = fn (Request $request) => in_array($request->ip(), config('polydock.trusted_ips', []), true);

        // Key on IP, not UUID: these limits exist to blunt enumeration, so a
        // per-UUID bucket (one fresh budget per guessed UUID) would defeat them.
        RateLimiter::for('register', fn (Request $request) => $isTrusted($request)
            ? Limit::none()
            : Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('public-read', fn (Request $request) => $isTrusted($request)
            ? Limit::none()
            : Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('instance-health', function (Request $request) use ($isTrusted) {
            if ($isTrusted($request)) {
                return Limit::none();
            }

            // A valid health token isn't guessing, so let it through unthrottled.
            $expectedToken = config('polydock.health_token');
            $suppliedToken = $request->query('token');
            if (! empty($expectedToken) && is_string($suppliedToken) && hash_equals((string) $expectedToken, $suppliedToken)) {
                return Limit::none();
            }

            return Limit::perMinute(120)->by($request->ip());
        });

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
