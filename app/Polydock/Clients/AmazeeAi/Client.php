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

    public function createToken(string $name, int $userId = 0): array
    {
        $data = ['name' => $name];
        if ($userId > 0) {
            $data['user_id'] = $userId;
        }

        return $this->post('/auth/token', $data);
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

    public function getRegion(int $regionId): array
    {
        return $this->get("/regions/{$regionId}");
    }

    public function createUser(string $email, string $password): array
    {
        return $this->post('/users', ['email' => $email, 'password' => $password]);
    }

    public function searchUsers(string $email): array
    {
        return $this->get('/users/search', ['email' => $email]);
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
