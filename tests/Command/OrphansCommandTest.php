<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Tests\Command;

use PedhotDev\NepotismFree\Debugger\Adapter\IntrospectionAdapter;
use PedhotDev\NepotismFree\Debugger\Command\OrphansCommand;
use PedhotDev\NepotismFree\Debugger\Tests\CommandTestCase;

/**
 * @covers \PedhotDev\NepotismFree\Debugger\Command\OrphansCommand
 */
class OrphansCommandTest extends CommandTestCase
{
    public function testOrphansAbortOnMissingDependency(): void
    {
        $container = $this->createMockContainer([
            \PedhotDev\NepotismFree\Debugger\Tests\Command\ValidateProcess::class => \PedhotDev\NepotismFree\Debugger\Tests\Command\ValidateProcess::class
        ]);

        $adapter = new IntrospectionAdapter($container);
        $command = new OrphansCommand($adapter);
        $tester = $this->createTester($command);

        $exitCode = $tester->execute([]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Cannot determine orphan services: graph contains missing dependencies.', $tester->getDisplay());
    }

    public function testOrphansSuccessWhenClean(): void
    {
        $container = $this->createMockContainer(['Healthy' => 'stdClass']);
        $adapter = new IntrospectionAdapter($container);
        $command = new OrphansCommand($adapter);
        $tester = $this->createTester($command);

        $exitCode = $tester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('No orphan services detected.', $tester->getDisplay());
    }
}
