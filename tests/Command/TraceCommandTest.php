<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Tests\Command;

use PedhotDev\NepotismFree\Debugger\Adapter\IntrospectionAdapter;
use PedhotDev\NepotismFree\Debugger\Command\TraceCommand;
use PedhotDev\NepotismFree\Debugger\Tests\CommandTestCase;

/**
 * @covers \PedhotDev\NepotismFree\Debugger\Command\TraceCommand
 */
class TraceCommandTest extends CommandTestCase
{
    public function testTraceServiceWithDependencies(): void
    {
        $container = $this->createMockContainer([
            'ServiceA' => 'stdClass',
        ]);
        
        $adapter = new IntrospectionAdapter($container);
        $command = new TraceCommand($adapter);
        $tester = $this->createTester($command);

        $tester->execute(['service' => 'ServiceA']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Resolution Trace for: ServiceA', $output);
        $this->assertStringContainsString('Direction: dependencies required by this service', $output);
    }

    public function testTraceLeafServiceShowsNoDependencies(): void
    {
        $container = $this->createMockContainer(['Leaf' => 'stdClass']);
        $adapter = new IntrospectionAdapter($container);
        $command = new TraceCommand($adapter);
        $tester = $this->createTester($command);

        $tester->execute(['service' => 'Leaf']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('(no dependencies)', $output);
    }

    public function testTraceNonExistentServiceShowsSuggestions(): void
    {
        $container = $this->createMockContainer(['ExistingService' => 'stdClass']);
        $adapter = new IntrospectionAdapter($container);
        $command = new TraceCommand($adapter);
        $tester = $this->createTester($command);

        $tester->execute(['service' => 'ExistingServic']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Service \'ExistingServic\' not found.', $output);
        $this->assertStringContainsString('Did you mean:', $output);
        $this->assertStringContainsString('ExistingService', $output);
        $this->assertStringContainsString('Hint:', $output);
    }
}
