<?php

namespace App\PolydockEngine\Traits;

use Exception;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\Exceptions\PolydockEngineProcessPolydockAppInstanceException;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

trait PolydockEnginePreCreateTrait
{
     /**
     * Run the pre-create step
     * @return void
     */
    protected function processPolydockAppInstancePreCreate(): bool
    {

        $this->info('Calling processPolydockAppInstancePreCreate', ['engine' => self::class, 'location' => 'processPolydockAppInstancePreCreate']);

        $polydockApp = null;
        $location = __FUNCTION__;
        $engine = self::class;
        $outputContext = ['engine' => $engine, 'location' => $location];

        try {
            $polydockApp = $this->appInstance->getApp();
        } catch(Exception $e) {
            $this->error('Pre-create failed - app instance not found', $outputContext + ['exception' => $e]);
            $this->appInstance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_FAILED);
            return false;
        }

        try {
            $polydockApp->info('Checking status before processing', $outputContext);
            $this->requirePolydockAppInstanceStatus(PolydockAppInstanceStatus::PENDING_PRE_CREATE);
            $polydockApp->info('Checked status before processing ok', $outputContext);
            
            $polydockApp->info('Calling preCreateAppInstance', $outputContext);
            $polydockApp->preCreateAppInstance($this->appInstance);
            $polydockApp->info('preCreateAppInstance completed without exception', $outputContext);

            $polydockApp->info('Checking status after processing', $outputContext);
            $this->requirePolydockAppInstanceStatus(PolydockAppInstanceStatus::PRE_CREATE_COMPLETED);
            $polydockApp->info('Checked status after processing ok', $outputContext);
            return true;
        } 
        catch(PolydockAppInstanceStatusFlowException $e) {
            $polydockApp->error('Pre-create failed - status flow exception', $outputContext + ['exception' => $e]);
            if($this->appInstance->getStatus() !== PolydockAppInstanceStatus::PRE_CREATE_FAILED) {
                $polydockApp->info('Forcing status to PRE_CREATE_FAILED', $outputContext);
                $this->appInstance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_FAILED);
            }
            return false;
        }
        catch(PolydockEngineProcessPolydockAppInstanceException $e) {
            $polydockApp->error('Pre-create failed - process exception', $outputContext + ['exception' => $e]);
            if($this->appInstance->getStatus() !== PolydockAppInstanceStatus::PRE_CREATE_FAILED) {
                $polydockApp->info('Forcing status to PRE_CREATE_FAILED', $outputContext);
                $this->appInstance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_FAILED);
            }
        } catch(Exception $e) {
            $polydockApp->error('Pre-create failed - unknown exception', $outputContext + ['exception' => $e]);
            $this->appInstance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_FAILED);
        }

        return false;
    }
}