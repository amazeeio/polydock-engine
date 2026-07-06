<?php

namespace App\Polydock\Clients\Lagoon\ClientTraits;

use App\Polydock\Clients\Lagoon\LagoonClientInitializeRequiredToInteractException;
use App\Polydock\Clients\Lagoon\Ssh;

/**
 * Trait AuthTrait
 *
 * Provides authentication and API interaction methods for the Lagoon API client.
 */
trait AuthTrait
{
    public function getLagoonTokenOverSSH(bool $refresh = false, bool $debug = false): string
    {
        if ($this->lagoonToken && ! $refresh) {
            return $this->lagoonToken;
        }

        $ssh = Ssh::createLagoonConfigured($this->lagoonSshUser, $this->lagoonSshServer, $this->lagoonSshPort, $this->sshPrivateKeyFile);

        if ($debug) {
            echo $ssh->getTokenCommand();
        }

        $token = $ssh->executeLagoonGetToken();
        $this->setLagoonToken($token);

        return $token;
    }

    /**
     * Pings the Lagoon API to verify connectivity and authentication
     *
     * @return bool True if connection is successful, false otherwise
     *
     * @throws LagoonClientInitializeRequiredToInteractException If client is not properly initialized
     */
    public function pingLagoonAPI(): bool
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        /**
         * Query Example
         */
        $query = '
          query q {
            lagoonVersion
            me {
              id
            }
          }';

        $response = $this->graphqlClient->query($query);

        if ($response->hasErrors()) {
            return false;
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();

            return isset($data['lagoonVersion']) && isset($data['me']['id']);
        }
    }

    /**
     * Retrieves information about the currently authenticated user
     *
     * @return array User information including ID and email
     *
     * @throws LagoonClientInitializeRequiredToInteractException If client is not properly initialized
     */
    public function whoAmI(): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        /**
         * Query Example
         */
        $query = '
          query q {
            lagoonVersion
            me {
	      id,
	      email
            }
          }';

        $response = $this->graphqlClient->query($query);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            return $response->getData();
        }
    }
}
