<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Tests;

use PHPUnit\Framework\TestCase;
use PedhotDev\NepotismFree\Debugger\Adapter\IntrospectionAdapter;
use PedhotDev\NepotismFree\Core\Container;
use PedhotDev\NepotismFree\Core\Registry;
use PedhotDev\NepotismFree\Core\ModuleAccessPolicy;
use Symfony\Component\Console\Tester\CommandTester;

abstract class CommandTestCase extends TestCase
{
    protected function createTester(\Symfony\Component\Console\Command\Command $command, ?Container $container = null): CommandTester
    {
        return new CommandTester($command);
    }

    protected function createMockContainer(array $bindings = [], array $scopes = []): Container
    {
        $registry = new Registry();
        foreach ($bindings as $id => $impl) {
            $registry->bind($id, $impl);
        }
        foreach ($scopes as $id => $scope) {
            $registry->setScope($id, $scope);
        }
        
        $policy = new ModuleAccessPolicy($registry);
        return new Container($registry, $policy);
    }
}
