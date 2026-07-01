<?php

namespace App\Jobs;

use App\Models\PolydockDeploymentRun;
use App\Services\PolydockDeploymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Polls a single deployment run's Lagoon bulk deployment once, updating the run's
 * counts/status and each instance's cached deploy state. Repeated polling until a
 * run reaches a terminal state is driven by the scheduled poll command.
 */
class PollDeploymentRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
