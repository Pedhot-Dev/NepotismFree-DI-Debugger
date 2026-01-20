<?php

namespace Tests\Fixtures;

use PedhotDev\NepotismFree\Core\Container;
use PedhotDev\NepotismFree\Contract\IntrospectableContainerInterface;


class MockIntrospectableContainer extends Container implements IntrospectableContainerInterface
{
    public function getDependencyGraph(): \PedhotDev\NepotismFree\Introspection\DependencyGraph
    {
        $node = new \PedhotDev\NepotismFree\Introspection\ServiceNode(
            id: 'RootService',
            type: 'singleton',
            isResolved: true,
            concrete: 'Tests\Fixtures\Classes\RootService'
        );

        return new \PedhotDev\NepotismFree\Introspection\DependencyGraph([
            'RootService' => $node
        ]);
    }
}
