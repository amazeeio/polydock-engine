<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\Commands\BaseCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ConsoleCommandsTest extends TestCase
{
    /**
     * Assert that all custom console commands inherit from BaseCommand.
     */
    public function test_all_custom_commands_inherit_from_base_command(): void
    {
        $commandsPath = realpath(__DIR__.'/../../../app/Console/Commands');
        $this->assertDirectoryExists($commandsPath);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($commandsPath)
        );

        $count = 0;
        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($commandsPath.DIRECTORY_SEPARATOR, '', $file->getRealPath());
            $className = 'App\\Console\\Commands\\'.str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $relativePath);

            if (! class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract()) {
                continue;
            }

            $this->assertTrue(
                is_subclass_of($className, BaseCommand::class),
                sprintf('Console command [%s] must extend %s', $className, BaseCommand::class)
            );
            $count++;
        }

        $this->assertGreaterThan(0, $count, 'No console commands were found or tested.');
    }
}
