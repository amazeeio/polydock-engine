<?php

namespace App\Polydock\Apps\Generic\Traits\Health;

use App\Polydock\Core\PolydockAppInstanceInterface;

trait PollHealthProgressAppInstanceTrait
{
    public function pollAppInstanceHealthStatus(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $logContext = $this->getLogContext(__FUNCTION__);
        $appInstance->warning('TODO: Implement health check logic', $logContext);

        return $appInstance;
    }
}
