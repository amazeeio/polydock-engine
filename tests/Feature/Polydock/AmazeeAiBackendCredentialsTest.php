<?php

declare(strict_types=1);

namespace Tests\Feature\Polydock;

use App\Polydock\Apps\Generic\PolydockAiApp;
use App\Polydock\Clients\AmazeeAi\Client;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;
use Illuminate\Support\Facades\Http;
use Tests\Doubles\DoublePolydockAppInstance;
use Tests\TestCase;

class TestableAiApp extends PolydockAiApp
{
    public function setBackendClient(Client $client): void
    {
        $this->amazeeAiBackendClient = $client;
    }
}

/**
 * getPrivateAICredentialsFromBackend() provisions the AI keys trials run on.
 * The AmazeeAi client uses the Http facade, so the whole flow (auth check,
 * health ping, user resolution, key creation, response validation) is
 * exercised against Http::fake — no fake client class needed.
 */
class AmazeeAiBackendCredentialsTest extends TestCase
{
    private const BASE = 'http://amazee-ai.test';

    private function validKeysResponse(): array
    {
        return [
            'name' => 'proj-creds',
            'region' => 'us-east',
            'database_name' => 'db1',
            'database_host' => 'db.host',
            'database_username' => 'dbuser',
            'database_password' => 'dbpass',
            'litellm_token' => 'sk-token',
            'litellm_api_url' => 'https://llm.example',
        ];
    }

    private function app(): TestableAiApp
    {
        $app = new TestableAiApp('AI Test App', 'desc', 'author', 'https://example.com', 'support@example.com');
        $app->setBackendClient(new Client(self::BASE, 'test-token'));

        return $app;
    }

    private function makeInstanceDouble(array $data = []): DoublePolydockAppInstance
    {
        return new DoublePolydockAppInstance(null, $data + [
            'lagoon-project-name' => 'proj1',
            'amazee-ai-backend-region-id' => 3,
        ]);
    }

    private function fakeBackend(array $overrides = []): void
    {
        Http::fake($overrides + [
            self::BASE.'/auth/me' => Http::response(['is_admin' => true, 'is_active' => true]),
            self::BASE.'/health' => Http::response(['status' => 'healthy']),
            self::BASE.'/users/search*' => Http::response([['id' => 42, 'email' => 'existing@example.com']]),
            self::BASE.'/users' => Http::response(['id' => 77, 'email' => 'created@example.com']),
            self::BASE.'/private-ai-keys' => Http::response($this->validKeysResponse()),
        ]);
    }

    public function test_creates_keys_for_an_existing_backend_user(): void
    {
        $this->fakeBackend();

        $credentials = $this->app()->getPrivateAICredentialsFromBackend(
            $this->makeInstanceDouble(['amazee-ai-backend-user-email' => 'existing@example.com']),
        );

        $this->assertSame('sk-token', $credentials['litellm_token']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/private-ai-keys')
            && $request['owner_id'] === 42
            && $request['region_id'] === 3);

        // Existing user found -> no user creation.
        Http::assertNotSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/users'));
    }

    public function test_creates_a_backend_user_when_none_exists(): void
    {
        $this->fakeBackend([
            self::BASE.'/users/search*' => Http::response([]),
        ]);

        $this->app()->getPrivateAICredentialsFromBackend(
            $this->makeInstanceDouble(['amazee-ai-backend-user-email' => 'new@example.com']),
        );

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/users')
            && $request['email'] === 'new@example.com');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/private-ai-keys')
            && $request['owner_id'] === 77);
    }

    public function test_falls_back_to_the_anonymous_autogen_identity_without_an_email(): void
    {
        $this->fakeBackend();

        $this->app()->getPrivateAICredentialsFromBackend($this->makeInstanceDouble());

        Http::assertSent(fn ($request) => str_contains($request->url(), '/users/search')
            && str_contains($request->url(), urlencode('proj1@autogen.null')));
    }

    public function test_missing_region_id_fails_the_flow(): void
    {
        $this->fakeBackend();

        $this->expectException(PolydockAppInstanceStatusFlowException::class);

        $this->app()->getPrivateAICredentialsFromBackend(
            new DoublePolydockAppInstance(null, ['lagoon-project-name' => 'proj1']),
        );
    }

    public function test_incomplete_credential_response_fails_loudly(): void
    {
        $incomplete = $this->validKeysResponse();
        unset($incomplete['litellm_token']);
        $this->fakeBackend([
            self::BASE.'/private-ai-keys' => Http::response($incomplete),
        ]);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->expectExceptionMessage('litellm_token');

        $this->app()->getPrivateAICredentialsFromBackend($this->makeInstanceDouble());
    }

    public function test_non_admin_backend_token_is_rejected(): void
    {
        $this->fakeBackend([
            self::BASE.'/auth/me' => Http::response(['is_admin' => false, 'is_active' => true]),
        ]);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);

        $this->app()->getPrivateAICredentialsFromBackend($this->makeInstanceDouble());
    }
}
