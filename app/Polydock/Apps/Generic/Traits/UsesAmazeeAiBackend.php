<?php

namespace App\Polydock\Apps\Generic\Traits;

use App\Polydock\Clients\AmazeeAi\Client;
use App\Polydock\Clients\AmazeeAi\Exception\HttpException;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

trait UsesAmazeeAiBackend
{
    /**
     * Sets the lagoon client from the app instance.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to set the lagoon client from
     *
     * @throws PolydockAppInstanceStatusFlowException If lagoon client is not found
     */
    public function setAmazeeAiBackendClientFromAppInstance(PolydockAppInstanceInterface $appInstance): void
    {
        $engine = $appInstance->getEngine();
        $this->engine = $engine;

        $amazeeAiBackendClientProvider = $engine->getPolydockServiceProviderSingletonInstance('PolydockServiceProviderAmazeeAiBackend');
        $this->amazeeAiBackendClientProvider = $amazeeAiBackendClientProvider;

        if (! method_exists($amazeeAiBackendClientProvider, 'getAmazeeAiBackendClient')) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend client provider does not have getAmazeeAiBackendClient method');
        } else {
            // TODO: Fix this, this is a hack to get around the fact that the lagoon client provider is not typed
            /** @phpstan-ignore-next-line */
            $this->amazeeAiBackendClient = $this->amazeeAiBackendClientProvider->getAmazeeAiBackendClient();
        }

        if (! $this->amazeeAiBackendClient) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend client not found');
        }

        if (! ($this->amazeeAiBackendClient instanceof Client)) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend client is not an instance of '.Client::class);
        }

        $region = $appInstance->getKeyValue('amazee-ai-backend-region-id');
        if (! $region) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend region is required to be set in the app instance');
        }

        if (! $this->pingAmazeeAiBackend()) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not healthy');
        }

        if (! $this->checkAmazeeAiBackendAuth()) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not authorized');
        }
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function checkAmazeeAiBackendAuth(): bool
    {
        $logContext = $this->getLogContext(__FUNCTION__);

        $this->info('Checking amazeeAI backend auth', $logContext);

        $response = $this->amazeeAiBackendClient->getMe();

        if (! $response['is_admin']) {
            $this->error('Amazee AI backend is not authorized as an admin', $logContext + $response);
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not authorized as an admin');
        }

        if (! $response['is_active']) {
            $this->error('Amazee AI backend is not an active admin', $logContext + $response);
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not an active admin');
        }

        $this->info('Amazee AI backend is authorized and active', $logContext + $response);

        return true;
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function pingAmazeeAiBackend(): bool
    {
        $logContext = $this->getLogContext(__FUNCTION__);

        if (! $this->amazeeAiBackendClient) {
            throw new PolydockAppInstanceStatusFlowException('amazeeAI backend client not found for ping');
        }

        try {
            $response = $this->amazeeAiBackendClient->health();

            if (is_array($response) && isset($response['status'])) {
                if ($response['status'] === 'healthy') {
                    $this->info('amazeeAI backend is healthy', $logContext + $response);

                    return true;
                } else {
                    $this->error('amazeeAI backend is not healthy: ', $logContext + $response);

                    return false;
                }
            } else {
                $this->error('Error pinging amazeeAI backend: ', $logContext + $response);

                return false;
            }
        } catch (\Exception $e) {
            $this->error('Error pinging amazeeAI backend: ', $logContext + ['error' => $e->getMessage()]);
            throw new PolydockAppInstanceStatusFlowException('Error pinging Lagoon API: '.$e->getMessage());
        }
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function getPrivateAICredentialsFromBackend(PolydockAppInstanceInterface $appInstance): array
    {
        $logContext = $this->getLogContext(__FUNCTION__);

        if (! $this->checkAmazeeAiBackendAuth()) {
            $this->error('Amazee AI backend is not authorized', $logContext);
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not authorized');
        }

        if (! $this->pingAmazeeAiBackend()) {
            $this->error('Amazee AI backend is not healthy', $logContext);
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not healthy');
        }

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $region = $appInstance->getKeyValue('amazee-ai-backend-region-id');
        if (! $region) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend region is required to be set in the app instance');
        }

        $amazeeAiBackendUserEmail = $appInstance->getKeyValue('amazee-ai-backend-user-email');
        if (! $amazeeAiBackendUserEmail) {
            $amazeeAiBackendUserEmail = $projectName.'@autogen.null';
        }

        $logContext['ai_backend_region'] = $region;
        $logContext['ai_backend_user_email'] = $amazeeAiBackendUserEmail;

        $this->info('Searching for user in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
        $backendUserList = $this->amazeeAiBackendClient->searchUsers($amazeeAiBackendUserEmail);
        $this->info('Found '.count($backendUserList).' users in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);

        $backendUser = null;
        try {
            if (count($backendUserList) >= 1) {
                if (count($backendUserList) > 1) {
                    $this->info('Multiple users found in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
                } else {
                    $this->info('User found in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
                }
                $backendUser = $backendUserList[0];
                $this->info('Using user found in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
            } else {
                $this->info('No user found in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
                $password = bin2hex(random_bytes(16));
                $this->info('Creating new user in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
                $backendUser = $this->amazeeAiBackendClient->createUser($amazeeAiBackendUserEmail, $password);
                $this->info('Created new user in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
            }
        } catch (HttpException $e) {
            $this->error('Error creating user in amazeeAI backend', $logContext + [
                'status_code' => $e->getStatusCode(),
                'response' => $e->getResponse(),
            ]);
        }

        if (! $backendUser) {
            $this->error('Failed to create user in amazeeAI backend', $logContext);
            throw new PolydockAppInstanceStatusFlowException('Failed to create user in amazeeAI backend');
        }

        $backendUserId = $backendUser['id'];
        $backendCredentialName = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $projectName))).'-proj-creds';

        $logContext['ai_backend_user_id'] = $backendUserId;
        $logContext['ai_backend_credential_name'] = $backendCredentialName;

        $this->info('Getting private AI credentials from amazeeAI backend', $logContext);

        $response = $this->amazeeAiBackendClient->createPrivateAIKeys($region, $backendCredentialName, $backendUserId);

        if (! $response || ! is_array($response)) {
            $this->error('No private AI credentials found', $logContext);
            throw new PolydockAppInstanceStatusFlowException('No private AI credentials found');
        }

        $requiredKeys = [
            'name',
            'region',
            'database_name',
            'database_host',
            'database_username',
            'database_password',
            'litellm_token',
            'litellm_api_url',
        ];

        $redactedResponse = $response;
        foreach (['database_username', 'database_password', 'litellm_token', 'litellm_api_url'] as $sensitiveKey) {
            if (isset($redactedResponse[$sensitiveKey])) {
                $redactedResponse[$sensitiveKey] = '[REDACTED]';
            }
        }

        foreach ($requiredKeys as $key) {
            if (! isset($response[$key])) {
                $this->error('Missing required credential key: '.$key, $logContext + $redactedResponse);
                throw new PolydockAppInstanceStatusFlowException('Missing required credential key: '.$key);
            }
        }

        $this->info('Private AI credentials found', $logContext + $redactedResponse);

        return $response;
    }
}
