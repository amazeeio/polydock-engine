<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppBase;
use App\Polydock\Core\PolydockAppInstanceInterface;

/**
 * Records which lifecycle method the engine dispatched and moves the instance
 * to the status the engine expects (or a scripted override), so Engine's
 * dispatch table can be tested without Lagoon.
 *
 * State is static because the engine constructs the app itself from the store
 * app's class name — the test can't inject an instance.
 */
class TestLifecycleApp extends PolydockAppBase
{
    /** @var list<string> */
    public static array $calls = [];

    /** @var array<string, PolydockAppInstanceStatus> */
    public static array $statusOverrides = [];

    /** @var array<string, \Exception> */
    public static array $throwOn = [];

    private const SUCCESS_STATUS = [
        'preCreateAppInstance' => PolydockAppInstanceStatus::PRE_CREATE_COMPLETED,
        'createAppInstance' => PolydockAppInstanceStatus::CREATE_COMPLETED,
        'postCreateAppInstance' => PolydockAppInstanceStatus::POST_CREATE_COMPLETED,
        'preDeployAppInstance' => PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED,
        'deployAppInstance' => PolydockAppInstanceStatus::DEPLOY_RUNNING,
        'postDeployAppInstance' => PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED,
        'preRemoveAppInstance' => PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED,
        'removeAppInstance' => PolydockAppInstanceStatus::REMOVE_COMPLETED,
        'postRemoveAppInstance' => PolydockAppInstanceStatus::POST_REMOVE_COMPLETED,
        'preUpgradeAppInstance' => PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED,
        'upgradeAppInstance' => PolydockAppInstanceStatus::UPGRADE_COMPLETED,
        'postUpgradeAppInstance' => PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED,
        'claimAppInstance' => PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED,
        'pollAppInstanceDeploymentProgress' => PolydockAppInstanceStatus::DEPLOY_COMPLETED,
        'pollAppInstanceUpgradeProgress' => PolydockAppInstanceStatus::UPGRADE_COMPLETED,
        'pollAppInstanceHealthStatus' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
    ];

    public static function reset(): void
    {
        self::$calls = [];
        self::$statusOverrides = [];
        self::$throwOn = [];
    }

    public static function getAppDefaultVariableDefinitions(): array
    {
        return [];
    }

    public static function getAppVersion(): string
    {
        return '0.0.1-test';
    }

    private function record(string $method, PolydockAppInstanceInterface $appInstance): void
    {
        self::$calls[] = $method;

        if (isset(self::$throwOn[$method])) {
            throw self::$throwOn[$method];
        }

        $status = self::$statusOverrides[$method] ?? self::SUCCESS_STATUS[$method];
        $appInstance->setStatus($status);
    }

    public function preCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function createAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function postCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function preDeployAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function deployAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function postDeployAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function preRemoveAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function removeAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function postRemoveAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function preUpgradeAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function upgradeAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function postUpgradeAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function claimAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function pollAppInstanceDeploymentProgress(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function pollAppInstanceUpgradeProgress(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }

    public function pollAppInstanceHealthStatus(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->record(__FUNCTION__, $appInstance);

        return $appInstance;
    }
}
