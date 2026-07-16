<?php

namespace App\Polydock\Clients\Lagoon;

use App\Polydock\Clients\Lagoon\ClientTraits\AuthTrait;
use App\Polydock\Clients\Lagoon\ClientTraits\GroupTrait;
use App\Polydock\Clients\Lagoon\ClientTraits\OrganizationTrait;
use App\Polydock\Clients\Lagoon\ClientTraits\ProjectEnvironmentTrait;
use App\Polydock\Clients\Lagoon\ClientTraits\ProjectTrait;

/**
 * Client class for interacting with the Lagoon API
 *
 * This class provides methods to interact with Lagoon's GraphQL API, handling operations like:
 * - Project management (creation, deletion, deployment)
 * - Environment management
 * - Variable management
 * - Authentication
 *
 * It requires SSH key authentication and manages the GraphQL client connection.
 */
class Client
{
    protected ?GraphQLClient $graphqlClient = null;

    protected string $sshPrivateKeyFile;

    protected string $lagoonSshUser;

    protected string $lagoonSshServer;

    protected int $lagoonSshPort;

    protected ?string $lagoonToken = null;

    protected string $lagoonApiEndpoint;

    protected bool $debug = false;

    use AuthTrait;
    use GroupTrait;
    use OrganizationTrait;
    use ProjectEnvironmentTrait;
    use ProjectTrait;

    /**
     * Constructor for the Lagoon API client
     *
     * Initializes the client with configuration settings for SSH and API connectivity.
     * Uses default values for most settings if not explicitly provided.
     *
     * @param  array  $config  Configuration array with optional keys:
     *                         - ssh_user: SSH username (default:
     *                         'lagoon') - ssh_server: SSH server
     *                         hostname (default:
     *                         'ssh.lagoon.amazeeio.cloud') -
     *                         ssh_port: SSH port (default: '32222') -
     *                         endpoint: API endpoint URL (default:
     *                         'https://api.lagoon.amazeeio.cloud/graphql')
     *                         - ssh_private_key_file: Path to SSH
     *                         private key (default: '~/.ssh/id_rsa')
     *
     * @throws LagoonClientPrivateKeyNotFoundException
     */
    public function __construct(protected array $config = [])
    {
        $this->lagoonSshUser = $this->config['ssh_user'] ?? 'lagoon';
        $this->lagoonSshServer = $this->config['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud';
        $this->lagoonSshPort = Ssh::normalizePort($this->config['ssh_port'] ?? 32222);
        $this->lagoonApiEndpoint = $this->config['endpoint'] ?? 'https://api.lagoon.amazeeio.cloud/graphql';
        $this->sshPrivateKeyFile = $this->config['ssh_private_key_file'] ?? getenv('HOME').'/.ssh/id_rsa';

        if (! isset($this->config['debug'])) {
            $this->debug = false;
        } else {
            $this->debug = $this->config['debug'];
        }

        if (! file_exists($this->sshPrivateKeyFile)) {
            throw new LagoonClientPrivateKeyNotFoundException($this->sshPrivateKeyFile);
        }
    }

    /**
     * Set the debug mode
     *
     * @param  bool  $debug  True to enable debug, false to disable
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Get the debug mode
     *
     * @return bool True if debug is enabled, false otherwise
     */
    public function getDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Initializes the GraphQL client with authentication token
     *
     * @throws LagoonClientTokenRequiredToInitializeException if no token is set
     */
    public function initGraphqlClient(): void
    {
        if (empty($this->lagoonToken)) {
            throw new LagoonClientTokenRequiredToInitializeException;
        }

        $options = [];

        if (isset($this->config['connect_timeout'])) {
            $options['connect_timeout'] = (float) $this->config['connect_timeout'];
        } else {
            $options['connect_timeout'] = 5.0; // Default to 5 seconds
        }

        if (isset($this->config['timeout'])) {
            $options['timeout'] = (float) $this->config['timeout'];
        } else {
            $options['timeout'] = 10.0; // Default to 10 seconds
        }

        $this->graphqlClient = new GraphQLClient(
            $this->lagoonApiEndpoint,
            $this->lagoonToken,
            $options
        );
    }

    /**
     * Sets the Lagoon authentication token
     *
     * @param  string  $token  The authentication token
     */
    public function setLagoonToken(string $token): void
    {
        $this->lagoonToken = $token;
    }

    /**
     * Gets the current Lagoon authentication token
     *
     * @return string|null The current token or null if not set
     */
    public function getLagoonToken(): ?string
    {
        return $this->lagoonToken;
    }
}
