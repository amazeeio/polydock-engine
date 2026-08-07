<?php

namespace App\Jobs;

use App\Models\PolydockDeploymentRun;
use App\Services\PolydockDeploymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Polls a single deployment run's Lagoon bulk deployment once, updating the run's
 * counts/status and each instance's cached deploy state. Repeated polling until a
 * run reaches a terminal state is driven by the scheduled poll command.
 */
class PollDeploymentRunJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $deploymentRunId) {}

    public function handle(PolydockDeploymentService $service): void
    {
        $run = PolydockDeploymentRun::find($this->deploymentRunId);

        if (! $run || $run->isTerminal()) {
            return;
        }

        $service->pollRun($run);
    }
}
