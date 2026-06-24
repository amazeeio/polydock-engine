<?php

namespace App\Polydock\Apps\Generic\Traits\Upgrade;

use App\Polydock\Core\PolydockAppInstanceInterface;

trait PollUpgradeProgressAppInstanceTrait
{
    public function pollAppInstanceUpgradeProgress(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $logContext = $this->getLogContext(__FUNCTION__);
        $appInstance->warning('TODO: Implement upgrade progress logic', $logContext);

        return $appInstance;
    }
}
