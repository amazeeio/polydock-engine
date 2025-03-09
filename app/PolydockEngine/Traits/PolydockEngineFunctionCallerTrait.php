<?php

namespace App\PolydockEngine\Traits;

use Exception;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\Exceptions\PolydockEngineProcessPolydockAppInstanceException;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

trait PolydockEngineFunctionCallerTrait
{
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
      * @param string $appFunctionName The name of the function to call on the app instance
      * @param PolydockAppInstanceStatus $entryStatus The required status before processing
      * @param PolydockAppInstanceStatus $completedStatus The expected status after successful processing
      * @param PolydockAppInstanceStatus $failedStatus The status to set if processing fails
      * @return bool True if processing succeeded, false if it failed
      * @throws PolydockAppInstanceStatusFlowException If status requirements are not met
      */
    protected function processPolydockAppUsingFunction(string $appFunctionName, 
        PolydockAppInstanceStatus $entryStatus,
        PolydockAppInstanceStatus $completedStatus,
        PolydockAppInstanceStatus $failedStatus): bool
    {
        $polydockApp = null;
        $location = __FUNCTION__;
        $engine = self::class;
        $outputContext = ['engine' => $engine, 'location' => $location, 'appFunction' => $appFunctionName];

        $this->info('Initialising ' . $location . ' for ' . $appFunctionName, $outputContext);

        // Initialise the required resources
        try {
            $polydockApp = $this->appInstance->getApp();
            
            if(!$polydockApp) {
                $this->error($appFunctionName . ' failed - app instance not found', $outputContext);
                return false;
            }

            if(!method_exists($polydockApp, $appFunctionName)) {
                $this->error($appFunctionName . ' failed - app function not found', $outputContext);
                return false;
            }
        } catch(Exception $e) {
            $this->error($appFunctionName . ' failed - unknown initialisation exception', $outputContext + ['exception' => $e]);
            return false;
        }

        // Process the app instance
        try {
            $polydockApp->info($appFunctionName . ' Status-Check: before-processing', $outputContext);
            $this->requirePolydockAppInstanceStatus($entryStatus);
            $polydockApp->info($appFunctionName . ' Status-Check: before-processing ok', $outputContext);
            
            $polydockApp->info($appFunctionName . ' starting', $outputContext);
            $polydockApp->{$appFunctionName}($this->appInstance);
            $polydockApp->info($appFunctionName . ' completed without exception', $outputContext);

            $polydockApp->info($appFunctionName . ' Status-Check: after-processing', $outputContext);
            $this->requirePolydockAppInstanceStatus($completedStatus);
            $polydockApp->info($appFunctionName . ' Status-Check: after-processing ok', $outputContext);
            return true;
        } 
        catch(PolydockAppInstanceStatusFlowException $e) {
            $polydockApp->error($appFunctionName . ' failed - status flow exception', $outputContext + ['exception' => $e]);
            if($this->appInstance->getStatus() !== $failedStatus) {
                $polydockApp->info('Forcing status to ' . $failedStatus->value, $outputContext);
                $this->appInstance->setStatus($failedStatus);
            }
            return false;
        }
        catch(PolydockEngineProcessPolydockAppInstanceException $e) {
            $polydockApp->error($appFunctionName . ' failed - process exception', $outputContext + ['exception' => $e]);
            if($this->appInstance->getStatus() !== $failedStatus) {
                $polydockApp->info('Forcing status to ' . $failedStatus->value, $outputContext);
                $this->appInstance->setStatus($failedStatus);
            }
        } catch(Exception $e) {
            $polydockApp->error($appFunctionName . ' failed - unknown exception', $outputContext + ['exception' => $e]);
            $this->appInstance->setStatus($failedStatus);
        }

        return false;
    }

    protected function processPolydockAppPollUpdateUsingFunction(string $appFunctionName, 
        PolydockAppInstanceStatus $entryStatus,
        array $expectedStatuses): bool
    {
        $polydockApp = null;
        $location = __FUNCTION__;
        $engine = self::class;
        $outputContext = ['engine' => $engine, 'location' => $location, 'appFunction' => $appFunctionName];

        $this->info('Initialising ' . $location . ' for ' . $appFunctionName, $outputContext);

        // Initialise the required resources
        try {
            $polydockApp = $this->appInstance->getApp();
            
            if(!$polydockApp) {
                $this->error($appFunctionName . ' failed - app instance not found', $outputContext);
                return false;
            }

            if(!method_exists($polydockApp, $appFunctionName)) {
                $this->error($appFunctionName . ' failed - app function not found', $outputContext);
                return false;
            }
        } catch(Exception $e) {
            $this->error($appFunctionName . ' failed - unknown initialisation exception', $outputContext + ['exception' => $e]);
            return false;
        }
        
        // Poll the app instance
        try {
            $polydockApp->info($appFunctionName . ' Status-Check: before-processing', $outputContext);
            if($this->appInstance->getStatus() !== $entryStatus) {
                $polydockApp->info($appFunctionName . ' Status-Check: before-processing skipped - status not as expected', $outputContext);
                return false;
            }
            $polydockApp->info($appFunctionName . ' Status-Check: before-processing ok', $outputContext);
            
            $polydockApp->info($appFunctionName . ' starting', $outputContext);
            $polydockApp->{$appFunctionName}($this->appInstance);
            $polydockApp->info($appFunctionName . ' completed without exception', $outputContext);

            $polydockApp->info($appFunctionName . ' Status-Check: after-processing', $outputContext);
            $this->requirePolydockAppInstanceStatusOneOfList($expectedStatuses);
            $polydockApp->info($appFunctionName . ' Status-Check: after-processing ok', $outputContext);
            return true;
        } 
        catch(PolydockAppInstanceStatusFlowException $e) {
            $polydockApp->error($appFunctionName . ' failed - status flow exception', $outputContext + ['exception' => $e]);
            return false;
        }
        catch(PolydockEngineProcessPolydockAppInstanceException $e) {
            $polydockApp->error($appFunctionName . ' failed - process exception', $outputContext + ['exception' => $e]);
            return false;   
        }
        catch(Exception $e) {
            $polydockApp->error($appFunctionName . ' failed - unknown exception', $outputContext + ['exception' => $e]);
            return false;
        }
        
        return true;
    }
}