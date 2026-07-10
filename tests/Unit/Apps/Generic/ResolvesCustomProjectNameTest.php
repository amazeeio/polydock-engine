<?php

namespace Tests\Unit\Apps\Generic;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Apps\Generic\Traits\Create\ResolvesCustomProjectNameTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FakeProjectExistsLagoonClient
{
    public function __construct(public int $collisions = 0) {}

    public function projectExistsByName(string $name): bool
    {
        return $this->collisions-- > 0;
    }
}

class ResolvesCustomProjectNameHarness
{
    use ResolvesCustomProjectNameTrait {
        finalizeCustomProjectName as public;
    }

    public object $lagoonClient;

    public function info(string $message, array $context = []): void {}

    public function error(string $message, array $context = []): void {}
}

class ClawLikeNamingHarness extends ResolvesCustomProjectNameHarness
{
    public static function defaultProjectNamingAdjectives(): array
    {
        return ['pinchy'];
    }

    public static function defaultProjectNamingNouns(): array
    {
        return ['lobster'];
    }
}

class ResolvesCustomProjectNameTest extends TestCase
{
    use RefreshDatabase;

    private function makeInstance(array $appConfig = [], ?string $requestedName = null): PolydockAppInstance
    {
        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);

        $store = PolydockStore::factory()->create(['lagoon_deploy_project_prefix' => 'claw']);
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'app_config' => $appConfig,
        ]);

        $instance = PolydockAppInstance::create(array_filter([
            'polydock_store_app_id' => $storeApp->id,
            'name' => $requestedName,
            'user_group_id' => null,
            'config' => [],
        ]));

        if ($requestedName !== null) {
            $instance->storeKeyValue('lagoon-project-name', $requestedName);
            $instance->save();
        }

        return $instance;
    }

    private function makeHarness(int $collisions = 0, bool $clawLike = false): ResolvesCustomProjectNameHarness
    {
        $harness = $clawLike ? new ClawLikeNamingHarness : new ResolvesCustomProjectNameHarness;
        $harness->lagoonClient = new FakeProjectExistsLagoonClient($collisions);

        return $harness;
    }

    public function test_requested_name_gets_prefixed_and_sanitized(): void
    {
        $instance = $this->makeInstance(requestedName: 'My Cool App!');
        $harness = $this->makeHarness();

        $harness->finalizeCustomProjectName($instance);

        $this->assertEquals('claw-my-cool-app', $instance->getKeyValue('lagoon-project-name'));
        $this->assertEquals('claw-my-cool-app', $instance->name);
    }

    public function test_already_prefixed_name_is_kept(): void
    {
        $instance = $this->makeInstance(requestedName: 'claw-existing-name');
        $harness = $this->makeHarness();

        $harness->finalizeCustomProjectName($instance);

        $this->assertEquals('claw-existing-name', $instance->getKeyValue('lagoon-project-name'));
    }

    public function test_lagoon_collision_generates_variant_from_store_app_word_lists(): void
    {
        $instance = $this->makeInstance(
            ['project_naming_adjectives' => ['zesty'], 'project_naming_nouns' => ['shrimp']],
            requestedName: 'taken-name',
        );
        $harness = $this->makeHarness(collisions: 1);

        $harness->finalizeCustomProjectName($instance);

        $this->assertMatchesRegularExpression(
            '/^claw-taken-name-zesty-shrimp-[0-9a-f]{6}$/',
            $instance->getKeyValue('lagoon-project-name'),
        );
    }

    public function test_lagoon_collision_falls_back_to_app_default_word_lists(): void
    {
        $instance = $this->makeInstance(requestedName: 'taken-name');
        $harness = $this->makeHarness(collisions: 1, clawLike: true);

        $harness->finalizeCustomProjectName($instance);

        $this->assertMatchesRegularExpression(
            '/^claw-taken-name-pinchy-lobster-[0-9a-f]{6}$/',
            $instance->getKeyValue('lagoon-project-name'),
        );
    }

    public function test_too_many_collisions_throws(): void
    {
        $instance = $this->makeInstance(requestedName: 'taken-name');
        $harness = $this->makeHarness(collisions: 12);

        $this->expectExceptionMessage('Failed to generate a unique project name for Lagoon');
        $harness->finalizeCustomProjectName($instance);
    }

    public function test_no_prefix_and_no_name_generates_polydock_fallback(): void
    {
        $instance = $this->makeInstance();
        $instance->storeKeyValue('lagoon-deploy-project-prefix', '');
        $instance->storeKeyValue('lagoon-project-name', '');
        $instance->save();

        $harness = $this->makeHarness();
        $harness->finalizeCustomProjectName($instance);

        $this->assertMatchesRegularExpression(
            '/^polydock-[a-z]+-[a-z]+-[0-9a-f]{6}$/',
            $instance->getKeyValue('lagoon-project-name'),
        );
    }
}
