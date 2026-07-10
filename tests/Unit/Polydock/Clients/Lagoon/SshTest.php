<?php

declare(strict_types=1);

namespace Tests\Unit\Polydock\Clients\Lagoon;

use App\Polydock\Clients\Lagoon\Ssh;
use PHPUnit\Framework\TestCase;

class SshTest extends TestCase
{
    public function test_command_for_execute_quotes_remote_command(): void
    {
        $ssh = Ssh::create('project-env', 'ssh.lagoon.example.com');

        $command = $ssh->getCommandForExecute(
            'umask 077 && cat > /tmp/.claw_env && /lagoon/polydock_claim.sh',
            'openclaw-gateway',
            'node'
        );

        // The remote command must be a single quoted argument, otherwise the
        // engine's local shell splits on && and runs the tail locally.
        $this->assertStringEndsWith(
            "service=openclaw-gateway container=node 'umask 077 && cat > /tmp/.claw_env && /lagoon/polydock_claim.sh'",
            $command
        );
    }
}
