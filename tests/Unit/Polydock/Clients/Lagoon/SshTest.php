<?php

declare(strict_types=1);

namespace Tests\Unit\Polydock\Clients\Lagoon;

use App\Polydock\Clients\Lagoon\Ssh;
use PHPUnit\Framework\TestCase;

class SshTest extends TestCase
{
    public function test_command_for_execute_quotes_remote_command(): void
    {
        $ssh = new Ssh('project-env', 'ssh.lagoon.example.com');

        $command = $ssh->getCommandForExecute(
            'umask 077 && cat > /tmp/.claw_env && /lagoon/polydock_claim.sh',
            'openclaw-gateway',
            'node'
        );

        // The remote command must be a single quoted argument, otherwise the
        // engine's local shell splits on && and runs the tail locally.
        $this->assertStringEndsWith(
            "'service=openclaw-gateway' 'container=node' 'umask 077 && cat > /tmp/.claw_env && /lagoon/polydock_claim.sh'",
            $command
        );
    }

    public function test_command_for_execute_quotes_target_service_and_container(): void
    {
        // Project name, service and container come from instance data; shell
        // metacharacters in them must stay inside one quoted argument.
        $ssh = new Ssh('proj;touch /tmp/pwned;-main', 'ssh.lagoon.example.com');

        $command = $ssh->getCommandForExecute('true', 'cli; echo INJECTED #', 'cli$(id)');

        self::assertStringContainsString("'proj;touch /tmp/pwned;-main@ssh.lagoon.example.com'", $command);
        self::assertStringContainsString("'service=cli; echo INJECTED #'", $command);
        self::assertStringContainsString("'container=cli$(id)'", $command);
    }

    public function test_token_command_quotes_target(): void
    {
        $ssh = new Ssh('proj;id', 'ssh.lagoon.example.com');

        self::assertStringEndsWith("'proj;id@ssh.lagoon.example.com' token", $ssh->getTokenCommand());
    }
}
