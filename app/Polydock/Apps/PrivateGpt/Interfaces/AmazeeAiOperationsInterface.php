<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Interfaces;

use App\Polydock\Apps\PrivateGpt\Generated\Dto\APIToken;
use App\Polydock\Apps\PrivateGpt\Generated\Dto\LlmKeysResponse;
use App\Polydock\Apps\PrivateGpt\Generated\Dto\TeamResponse;
use App\Polydock\Core\PolydockAppInstanceInterface;

interface AmazeeAiOperationsInterface
{
    /**
     * Setup AmazeeAi client from app instance configuration
     */
    public function setAmazeeAiClientFromAppInstance(PolydockAppInstanceInterface $appInstance): void;

    /**
     * Create team and setup administrator
     */
    public function createTeamAndSetupAdministrator(PolydockAppInstanceInterface $appInstance): TeamResponse;

    /**
     * Generate keys for a team
     *
     * @return array{team_id: string, backend_key: APIToken, llm_key: LlmKeysResponse}
     */
    public function generateKeysForTeam(PolydockAppInstanceInterface $appInstance, string $teamId): array;
}
