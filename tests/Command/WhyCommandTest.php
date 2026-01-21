<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Tests\Command;

use PedhotDev\NepotismFree\Debugger\Adapter\IntrospectionAdapter;
use PedhotDev\NepotismFree\Debugger\Command\WhyCommand;
use PedhotDev\NepotismFree\Debugger\Tests\CommandTestCase;

if (!class_exists('PedhotDev\NepotismFree\Debugger\Tests\Command\WhyRoot')) {
    class WhyRoot { public function __construct(WhyServiceA $a) {} }
    class WhyServiceA { public function __construct(WhyServiceB $b) {} }
    class WhyServiceB {}
}

/**
 * @covers \PedhotDev\NepotismFree\Debugger\Command\WhyCommand
 */
class WhyCommandTest extends CommandTestCase
{
    public function testWhyServiceShowsUpstreamDependencies(): void
    {
        // Use class names as IDs so the resolver can link them via typehints
        $container = $this->createMockContainer([
            WhyRoot::class => WhyRoot::class,
            WhyServiceA::class => WhyServiceA::class,
            WhyServiceB::class => WhyServiceB::class,
        ]);
        
        $adapter = new IntrospectionAdapter($container);
        $command = new WhyCommand($adapter);
        
        $tester = $this->createTester($command);
        $tester->execute(['service' => WhyServiceB::class]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Explaining why service is instantiated: ' . WhyServiceB::class, $output);
        $this->assertStringContainsString('- ' . WhyServiceA::class, $output);
    }

    public function testWhyRootServiceReportsNoIncomingDependencies(): void
    {
        $container = $this->createMockContainer(['Root' => 'stdClass']);
        $adapter = new IntrospectionAdapter($container);
        $command = new WhyCommand($adapter);
        $tester = $this->createTester($command);

        $tester->execute(['service' => 'Root']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('This service is not required by any other service (It is a root or unused).', $output);
    }
}
