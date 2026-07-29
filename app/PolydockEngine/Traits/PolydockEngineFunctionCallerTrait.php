<?php

namespace App\PolydockEngine\Traits;

use App\Models\PolydockAppInstance;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\Exceptions\PolydockEngineProcessPolydockAppInstanceException;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;
use App\Polydock\Core\PolydockAppInterface;
use Exception;

trait PolydockEngineFunctionCallerTrait
{
    /**
     * Resolve the app object for an instance and confirm it exposes the
     * requested function. Logs and returns null when it can't.
     *
     * @return PolydockAppInterface|null
     */
    private function resolveAppFunction(PolydockAppInstance $appInstance, string $appFunctionName, array $outputContext)
    {
        try {
            $polydockApp = $appInstance->getApp();

            if (! $polydockApp) {
                $this->error($appFunctionName.' failed - app instance not found', $outputContext);

                return null;
            }

            if (! method_exists($polydockApp, $appFunctionName)) {
                $this->error($appFunctionName.' failed - app function not found', $outputContext);

                return null;
            }

            return $polydockApp;
        } catch (Exception $e) {
            $this->error($appFunctionName.' failed - unknown initialisation exception', $outputContext + [
                'exception_message' => $e->getMessage(),
                'exception_class' => $e::class,
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Process a Polydock app function with status flow control
     *
     * This method handles executing a function on a Polydock app instance while managing the status flow:
     *
     * 1. Validates the app instance is in the expected entry status
     * 2. Calls the specified function on the app instance
     * 3. Verifies the status was updated to completed status
     * 4. If any errors occur, sets status to failed status
     *
     * @param  string  $appFunctionName  The name of the function to call on the app instance
     * @param  PolydockAppInstanceStatus  $entryStatus  The required status before processing
     * @param  PolydockAppInstanceStatus  $completedStatus  The expected status after successful processing
     * @param  PolydockAppInstanceStatus  $failedStatus  The status to set if processing fails
     * @return bool True if processing succeeded, false if it failed
     *
     * @throws PolydockAppInstanceStatusFlowException If status requirements are not met
     */
    protected function processPolydockAppUsingFunction(
        PolydockAppInstance $appInstance,
        string $appFunctionName,
        PolydockAppInstanceStatus $entryStatus,
        PolydockAppInstanceStatus $completedStatus,
        PolydockAppInstanceStatus $failedStatus,
    ): bool {
        $polydockApp = null;
        $location = __FUNCTION__;
        $engine = self::class;
        $outputContext = ['engine' => $engine, 'location' => $location, 'appFunction' => $appFunctionName];

        $this->info('Initialising '.$location.' for '.$appFunctionName, $outputContext);

        $polydockApp = $this->resolveAppFunction($appInstance, $appFunctionName, $outputContext);
        if (! $polydockApp) {
            return false;
        }

        // Process the app instance
        try {
            $polydockApp->info($appFunctionName.' Status-Check: before-processing', $outputContext);
            $this->requirePolydockAppInstanceStatus($entryStatus, $appInstance);
            $polydockApp->info($appFunctionName.' Status-Check: before-processing ok', $outputContext);

            $polydockApp->info($appFunctionName.' starting', $outputContext);
            $polydockApp->{$appFunctionName}($appInstance);
            $appInstance->save();
            $polydockApp->info($appFunctionName.' completed without exception', $outputContext);

            $polydockApp->info($appFunctionName.' Status-Check: after-processing', $outputContext);
            $this->requirePolydockAppInstanceStatus($completedStatus, $appInstance);
            $polydockApp->info($appFunctionName.' Status-Check: after-processing ok', $outputContext);

            return true;
        } catch (PolydockAppInstanceStatusFlowException $e) {
            $message = $appFunctionName.' failed - status flow exception';
            $context = $outputContext + [
                'exception_message' => $e->getMessage(),
                'exception_class' => $e::class,
            ];
            $this->error($message, $context);
            $polydockApp->error($message, $context);
            if ($appInstance->getStatus() !== $failedStatus) {
                $polydockApp->info('Forcing status to '.$failedStatus->value, $outputContext);
                $appInstance->logLine('error', $message, $context)->setStatus($failedStatus)->save();
            }

            return false;
        } catch (PolydockEngineProcessPolydockAppInstanceException $e) {
            $message = $appFunctionName.' failed - process exception';
            $context = $outputContext + [
                'exception_message' => $e->getMessage(),
                'exception_class' => $e::class,
                'exception_trace' => $e->getTraceAsString(),
            ];
            $this->error($message, $context);
            $polydockApp->error($message, $context);
            if ($appInstance->getStatus() !== $failedStatus) {
                $polydockApp->info('Forcing status to '.$failedStatus->value, $outputContext);
                $appInstance->logLine('error', $message, $context)->setStatus($failedStatus)->save();
            }

            return false;
        } catch (Exception $e) {
            $message = $appFunctionName.' failed - unknown exception';
            $context = $outputContext + [
                'exception_message' => $e->getMessage(),
                'exception_class' => $e::class,
                'exception_trace' => $e->getTraceAsString(),
            ];
            $this->error($message, $context);
            $polydockApp->error($message, $context);
            $appInstance->logLine('error', $message, $context)->setStatus($failedStatus)->save();
        }

        return false;
    }

    protected function processPolydockAppPollUpdateUsingFunction(
        PolydockAppInstance $appInstance,
        string $appFunctionName,
        PolydockAppInstanceStatus $entryStatus,
        array $expectedStatuses,
    ): bool {
        $polydockApp = null;
        $location = __FUNCTION__;
        $engine = self::class;
        $outputContext = ['engine' => $engine, 'location' => $location, 'appFunction' => $appFunctionName];

        $this->info('Initialising '.$location.' for '.$appFunctionName, $outputContext);

        $polydockApp = $this->resolveAppFunction($appInstance, $appFunctionName, $outputContext);
        if (! $polydockApp) {
            return false;
        }

        // Poll the app instance
        try {
            $polydockApp->info($appFunctionName.' Status-Check: before-processing', $outputContext);
            if ($appInstance->getStatus() !== $entryStatus) {
                $polydockApp->info(
                    $appFunctionName.' Status-Check: before-processing skipped - status not as expected',
                    $outputContext,
                );

                return false;
            }
            $polydockApp->info($appFunctionName.' Status-Check: before-processing ok', $outputContext);

            $polydockApp->info($appFunctionName.' starting', $outputContext);
            $polydockApp->{$appFunctionName}($appInstance);
            $appInstance->save();
            $polydockApp->info($appFunctionName.' completed without exception', $outputContext);

            $polydockApp->info($appFunctionName.' Status-Check: after-processing', $outputContext);
            $this->requirePolydockAppInstanceStatusOneOfList($expectedStatuses, $appInstance);
            $polydockApp->info($appFunctionName.' Status-Check: after-processing ok', $outputContext);

            return true;
        } catch (PolydockAppInstanceStatusFlowException $e) {
            $message = $appFunctionName.' failed - status flow exception';
            $context = $outputContext + [
                'exception_message' => $e->getMessage(),
                'exception_class' => $e::class,
            ];
            $this->error($message, $context);
            $polydockApp->error($message, $context);

            return false;
        } catch (PolydockEngineProcessPolydockAppInstanceException $e) {
            $message = $appFunctionName.' failed - process exception';
            $context = $outputContext + [
                'exception_message' => $e->getMessage(),
                'exception_class' => $e::class,
                'exception_trace' => $e->getTraceAsString(),
            ];
            $this->error($message, $context);
            $polydockApp->error($message, $context);

            return false;
        } catch (Exception $e) {
            $message = $appFunctionName.' failed - unknown exception';
            $context = $outputContext + [
                'exception_message' => $e->getMessage(),
                'exception_class' => $e::class,
                'exception_trace' => $e->getTraceAsString(),
            ];
            $this->error($message, $context);
            $polydockApp->error($message, $context);
        }

        return false;
    }
}
