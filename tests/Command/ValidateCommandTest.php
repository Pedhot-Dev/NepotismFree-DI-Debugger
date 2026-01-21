<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Tests\Command;

use PedhotDev\NepotismFree\Debugger\Adapter\IntrospectionAdapter;
use PedhotDev\NepotismFree\Debugger\Command\ValidateCommand;
use PedhotDev\NepotismFree\Debugger\Tests\CommandTestCase;
use PedhotDev\NepotismFree\Contract\Scope;

if (!class_exists('PedhotDev\NepotismFree\Debugger\Tests\Command\ValidateProcess')) {
    class ValidateProcess { public function __construct(ValidateTick $t) {} }
    class ValidateTick {}
}

if (!class_exists('PedhotDev\NepotismFree\Debugger\Tests\Command\CycleA')) {
    class CycleA { public function __construct(CycleB $b) {} }
    class CycleB { public function __construct(CycleA $a) {} }
}

/**
 * @covers \PedhotDev\NepotismFree\Debugger\Command\ValidateCommand
 */
class ValidateCommandTest extends CommandTestCase
{
    public function testValidateSuccessOnHealthyGraph(): void
    {
        $container = $this->createMockContainer(['Healthy' => 'stdClass']);
        $adapter = new IntrospectionAdapter($container);
        $command = new ValidateCommand($adapter);
        $tester = $this->createTester($command);

        $exitCode = $tester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Dependency graph is structurally sound.', $tester->getDisplay());
    }

    public function testValidateFailureOnScopeMismatch(): void
    {
        $container = $this->createMockContainer([
            ValidateProcess::class => ValidateProcess::class,
            ValidateTick::class => ValidateTick::class
        ], [
            ValidateProcess::class => Scope::PROCESS,
            ValidateTick::class => Scope::TICK
        ]);

        $adapter = new IntrospectionAdapter($container);
        $command = new ValidateCommand($adapter);
        $tester = $this->createTester($command);

        $exitCode = $tester->execute([]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Structural Errors', $tester->getDisplay());
        $this->assertStringContainsString('Scope mismatch detected:', $tester->getDisplay());
        $this->assertStringContainsString('PROCESS service ' . ValidateProcess::class . ' depends on TICK service ' . ValidateTick::class, $tester->getDisplay());
    }

    public function testValidateFailureOnCycle(): void
    {
        $container = $this->createMockContainer([
            CycleA::class => CycleA::class,
            CycleB::class => CycleB::class
        ]);

        $adapter = new IntrospectionAdapter($container);
        $command = new ValidateCommand($adapter);
        $tester = $this->createTester($command);

        $exitCode = $tester->execute([]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Structural Errors', $tester->getDisplay());
        $this->assertStringContainsString('Dependency cycle detected:', $tester->getDisplay());
    }

    public function testValidateJsonOutput(): void
    {
        $container = $this->createMockContainer([
            CycleA::class => CycleA::class,
            CycleB::class => CycleB::class
        ]);

        $adapter = new IntrospectionAdapter($container);
        $command = new ValidateCommand($adapter);
        $tester = $this->createTester($command);

        $exitCode = $tester->execute(['--format' => 'json']);

        $this->assertEquals(1, $exitCode);
        $data = json_decode($tester->getDisplay(), true);
        
        $this->assertEquals('invalid', $data['status']);
        $this->assertGreaterThan(0, $data['summary']['structural_errors']);
        $this->assertNotEmpty($data['structural_errors']);
        $this->assertEquals('dependency_cycle', $data['structural_errors'][0]['type']);
    }

    public function testValidateFailureOnMissingDependency(): void
    {
        $registry = new \PedhotDev\NepotismFree\Core\Registry();
        $registry->bind('A', 'stdClass');
        // A depends on B via constructor, but B is not bound.
        // We simulate this by manually creating the node in our mock setup if needed, 
        // but here we just need the adapter to report a node with a dependency that isn't a node.
        
        $container = $this->createMockContainer(['A' => 'stdClass']);
        // We need to make sure 'A' actually has 'B' as dependency in the graph.
        // The real introspection adapter gets this from the container's graph.
        // Since we are using the real Container in createMockContainer, 
        // we can use a class that has a dependency.
        
        $container = $this->createMockContainer([
            \PedhotDev\NepotismFree\Debugger\Tests\Command\ValidateProcess::class => \PedhotDev\NepotismFree\Debugger\Tests\Command\ValidateProcess::class
            // ValidateTick is NOT bound.
        ]);

        $adapter = new IntrospectionAdapter($container);
        $command = new ValidateCommand($adapter);
        $tester = $this->createTester($command);

        $exitCode = $tester->execute([]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Structural Errors', $tester->getDisplay());
        $this->assertStringContainsString('Missing dependency detected:', $tester->getDisplay());
        $this->assertStringContainsString(\PedhotDev\NepotismFree\Debugger\Tests\Command\ValidateProcess::class . ' -> ' . \PedhotDev\NepotismFree\Debugger\Tests\Command\ValidateTick::class, $tester->getDisplay());
    }

    public function testValidateJsonOutputWithMissingDependency(): void
    {
        $container = $this->createMockContainer([
            \PedhotDev\NepotismFree\Debugger\Tests\Command\ValidateProcess::class => \PedhotDev\NepotismFree\Debugger\Tests\Command\ValidateProcess::class
        ]);

        $adapter = new IntrospectionAdapter($container);
        $command = new ValidateCommand($adapter);
        $tester = $this->createTester($command);

        $exitCode = $tester->execute(['--format' => 'json']);

        $this->assertEquals(1, $exitCode);
        $data = json_decode($tester->getDisplay(), true);
        
        $this->assertEquals('invalid', $data['status']);
        $this->assertEquals('missing_dependency', $data['structural_errors'][0]['type']);
        $this->assertEquals(\PedhotDev\NepotismFree\Debugger\Tests\Command\ValidateProcess::class, $data['structural_errors'][0]['from']);
        $this->assertEquals(\PedhotDev\NepotismFree\Debugger\Tests\Command\ValidateTick::class, $data['structural_errors'][0]['dependency']);
    }
}
