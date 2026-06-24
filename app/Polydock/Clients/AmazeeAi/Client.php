<?php

declare(strict_types=1);

namespace App\Polydock\Clients\AmazeeAi;

use App\Polydock\Clients\AmazeeAi\Exception\HttpException;
use Illuminate\Support\Facades\Http;

class Client
{
    private readonly string $baseUrl;

    private array $headers;

    public function __construct(string $baseUrl, private readonly ?string $accessToken = null, private readonly bool $debug = false)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->accessToken) {
            $this->headers['Authorization'] = "Bearer {$this->accessToken}";
        }
    }

    public function login(string $email, string $password): array
    {
        return $this->post('/auth/login', [
            'username' => $email,
            'password' => $password,
        ]);
    }

    public function logout(): array
    {
        return $this->post('/auth/logout');
    }

    public function register(string $email, string $password): array
    {
        return $this->post('/auth/register', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function getMe(): array
    {
        return $this->get('/auth/me');
    }

    public function updateMe(array $data): array
    {
        return $this->put('/auth/me/update', $data);
    }

    public function createToken(string $name, int $userId = 0): array
    {
        $data = ['name' => $name];
        if ($userId > 0) {
            $data['user_id'] = $userId;
        }

        return $this->post('/auth/token', $data);
    }

    public function listTokens(): array
    {
        return $this->get('/auth/token');
    }

    public function deleteToken(string $tokenId): array
    {
        return $this->delete("/auth/token/{$tokenId}");
    }

    public function createPrivateAIKeys(int $regionId, string $name, int $userId = 0): array
    {
        $data = [
            'region_id' => $regionId,
            'name' => $name,
        ];

        if ($userId > 0) {
            $data['owner_id'] = $userId;
        }

        return $this->post('/private-ai-keys', $data);
    }

    public function createPrivateAIKeyToken(int $regionId, string $name, int $userId = 0, int $teamId = 0): array
    {
        $data = [
            'region_id' => $regionId,
            'name' => $name,
        ];

        if ($userId > 0) {
            $data['owner_id'] = $userId;
        }

        if ($teamId > 0) {
            $data['team_id'] = $teamId;
        }

        return $this->post('/private-ai-keys/token', $data);
    }

    public function listTeams(bool $includeDeleted = false): array
    {
        return $this->get('/teams', ['include_deleted' => $includeDeleted ? 'true' : 'false']);
    }

    public function getTeam(int $teamId, bool $includeDeleted = false): array
    {
        return $this->get("/teams/{$teamId}", ['include_deleted' => $includeDeleted ? 'true' : 'false']);
    }

    public function createTeam(string $name, string $adminEmail, ?string $phone = null, ?string $billingAddress = null, bool $forceUserKeys = false): array
    {
        $data = [
            'name' => $name,
            'admin_email' => $adminEmail,
            'force_user_keys' => $forceUserKeys,
        ];

        if ($phone !== null && $phone !== '') {
            $data['phone'] = $phone;
        }

        if ($billingAddress !== null && $billingAddress !== '') {
            $data['billing_address'] = $billingAddress;
        }

        return $this->post('/teams', $data);
    }

    public function listPrivateAIKeys(): array
    {
        return $this->get('/private-ai-keys');
    }

    public function deletePrivateAIKeys(string $keyName): array
    {
        return $this->delete("/private-ai-keys/{$keyName}");
    }

    public function listRegions(): array
    {
        return $this->get('/regions');
    }

    public function getRegion(int $regionId): array
    {
        return $this->get("/regions/{$regionId}");
    }

    public function createRegion(array $data): array
    {
        return $this->post('/regions', $data);
    }

    public function updateRegion(int $regionId, array $data): array
    {
        return $this->put("/regions/{$regionId}", $data);
    }

    public function deleteRegion(int $regionId): array
    {
        return $this->delete("/regions/{$regionId}");
    }

    public function listAdminRegions(): array
    {
        return $this->get('/regions/admin');
    }

    public function listUsers(): array
    {
        return $this->get('/users');
    }

    public function getUser(int $userId): array
    {
        return $this->get("/users/{$userId}");
    }

    public function createUser(string $email, string $password): array
    {
        return $this->post('/users', ['email' => $email, 'password' => $password]);
    }

    public function updateUser(int $userId, array $data): array
    {
        return $this->put("/users/{$userId}", $data);
    }

    public function deleteUser(int $userId): array
    {
        return $this->delete("/users/{$userId}");
    }

    public function searchUsers(string $email): array
    {
        return $this->get('/users/search', ['email' => $email]);
    }

    public function addUserToTeam(int $userId, int $teamId): array
    {
        return $this->post("/users/{$userId}/add-to-team", ['team_id' => $teamId]);
    }

    public function getAuditLogs(): array
    {
        return $this->get('/audit/logs');
    }

    public function getAuditLogsMetadata(): array
    {
        return $this->get('/audit/logs/metadata');
    }

    public function health(): array
    {
        return $this->get('/health');
    }

    private function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, [], $query);
    }

    private function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, $data);
    }

    private function put(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, $data);
    }

    private function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    private function request(string $method, string $path, array $data = [], array $query = []): array
    {
        $this->printDebug("Method: {$method}");
        $this->printDebug("Path: {$path}");
        $this->printDebug('Data: '.json_encode($data));
        $this->printDebug('Query: '.json_encode($query));

        $url = $this->baseUrl.$path;

        $response = Http::withHeaders($this->headers)
            ->send($method, $url, [
                'query' => $query,
                'json' => empty($data) ? null : $data,
            ]);

        $statusCode = $response->status();
        $decodedResponse = $response->json() ?? [];

        $this->printDebug('Response: '.$response->body());
        $this->printDebug('Decoded Response: '.json_encode($decodedResponse));

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new HttpException(
                $statusCode,
                "API request failed with status code: {$statusCode}",
                $decodedResponse
            );
        }

        return $decodedResponse;
    }

    private function printDebug(string $message): void
    {
        if ($this->debug) {
            echo 'amazeeai-backend-client-php: '.$message."\n";
        }
    }
}
