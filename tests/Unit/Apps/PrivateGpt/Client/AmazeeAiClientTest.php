<?php

declare(strict_types=1);

namespace Tests\Unit\Apps\PrivateGpt\Client;

use App\Polydock\Apps\PrivateGpt\Client\AmazeeAiClient;
use App\Polydock\Apps\PrivateGpt\Exceptions\AmazeeAiClientException;
use App\Polydock\Apps\PrivateGpt\Generated\Dto\RegionResponse;
use App\Polydock\Apps\PrivateGpt\Generated\Dto\TeamResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AmazeeAiClientTest extends TestCase
{
    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKey = 'test-api-key';
    }

    /**
     * Helper to create AmazeeAiClient with a mocked Guzzle client handler.
     */
    private function createClientWithMockHandler(MockHandler $mockHandler): AmazeeAiClient
    {
        $handlerStack = HandlerStack::create($mockHandler);
        // Mirror the real client's base_uri so relative request paths resolve.
        // Guzzle 7.13+ rejects relative URIs when no base_uri is configured.
        $httpClient = new Client(['handler' => $handlerStack, 'base_uri' => 'https://api.amazee.ai']);

        $client = new AmazeeAiClient($this->apiKey, 'https://api.amazee.ai');

        $reflection = new ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setValue($client, $httpClient);

        return $client;
    }

    /**
     * Test successful team creation
     */
    public function test_create_team_success(): void
    {
        $validResponse = json_encode([
            'id' => 123,
            'name' => 'test-team',
            'admin_email' => 'admin@example.com',
            'is_active' => true,
            'is_always_free' => false,
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-01T00:00:00Z',
            'phone' => null,
            'billing_address' => null,
            'last_payment' => null,
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $validResponse),
        ]);

        $client = $this->createClientWithMockHandler($mock);

        $result = $client->createTeam('test-team', 'admin@example.com');

        $this->assertInstanceOf(TeamResponse::class, $result);
        $this->assertSame(123, $result->id);
        $this->assertSame('test-team', $result->name);
        $this->assertSame('admin@example.com', $result->admin_email);
    }

    /**
     * Test createTeam wraps Guzzle RequestException in AmazeeAiClientException
     */
    public function test_create_team_request_exception(): void
    {
        $mock = new MockHandler([
            new RequestException('Server Error', new Request('POST', '/teams'), new Response(500)),
        ]);

        $client = $this->createClientWithMockHandler($mock);

        $this->expectException(AmazeeAiClientException::class);
        $this->expectExceptionMessage('Failed to create team');
        $client->createTeam('test-team', 'admin@example.com');
    }

    /**
     * Test createTeam wraps Guzzle ConnectException (network-level failure) in AmazeeAiClientException
     */
    public function test_create_team_network_failure(): void
    {
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', '/teams')),
        ]);

        $client = $this->createClientWithMockHandler($mock);

        $this->expectException(AmazeeAiClientException::class);
        $this->expectExceptionMessage('Failed to create team');
        $client->createTeam('test-team', 'admin@example.com');
    }

    /**
     * Test createTeam wraps Valinor response mapping validation errors in AmazeeAiClientException
     */
    public function test_create_team_validation_error(): void
    {
        // Missing required fields (e.g. name, admin_email) in JSON payload
        $invalidResponse = json_encode([
            'id' => 123,
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $invalidResponse),
        ]);

        $client = $this->createClientWithMockHandler($mock);

        $this->expectException(AmazeeAiClientException::class);
        $this->expectExceptionMessage('Failed to create team');
        $client->createTeam('test-team', 'admin@example.com');
    }

    /**
     * Test successful team deletion with standard message response
     */
    public function test_delete_team_success(): void
    {
        $responseBody = json_encode(['message' => 'Team deleted successfully']);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody ?: ''),
        ]);

        $client = $this->createClientWithMockHandler($mock);

        $result = $client->deleteTeam('team-123');

        $this->assertSame('Team deleted successfully', $result);
    }

    /**
     * Test successful team deletion when API returns 204 No Content (empty response)
     */
    public function test_delete_team_success_empty_body_204(): void
    {
        $mock = new MockHandler([
            new Response(204, [], ''),
        ]);

        $client = $this->createClientWithMockHandler($mock);

        $result = $client->deleteTeam('team-123');

        $this->assertSame('', $result);
    }

    /**
     * Test deleteTeam failure wraps exceptions
     */
    public function test_delete_team_failure(): void
    {
        $mock = new MockHandler([
            new RequestException('Not Found', new Request('DELETE', '/teams/team-123'), new Response(404)),
        ]);

        $client = $this->createClientWithMockHandler($mock);

        $this->expectException(AmazeeAiClientException::class);
        $this->expectExceptionMessage('Failed to delete team');
        $client->deleteTeam('team-123');
    }

    /**
     * Test successful regions retrieval
     */
    public function test_get_regions_success(): void
    {
        $responseBody = json_encode([
            [
                'name' => 'us-east',
                'postgres_host' => 'host1',
                'postgres_admin_user' => 'admin',
                'postgres_admin_password' => 'password',
                'litellm_api_url' => 'http://litellm',
                'litellm_api_key' => 'key',
                'id' => 1,
                'created_at' => '2024-01-01T00:00:00Z',
            ],
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody ?: ''),
        ]);

        $client = $this->createClientWithMockHandler($mock);

        $result = $client->getRegions();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(RegionResponse::class, $result[0]);
        $this->assertSame('us-east', $result[0]->name);
        $this->assertSame(1, $result[0]->id);
    }

    /**
     * Test getRegions throws exception on invalid or empty response
     */
    public function test_get_regions_invalid_response_throws_exception(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], ''),
        ]);

        $client = $this->createClientWithMockHandler($mock);

        $this->expectException(AmazeeAiClientException::class);
        $this->expectExceptionMessage('Failed to get regions: Invalid or empty API response');
        $client->getRegions();
    }
}
