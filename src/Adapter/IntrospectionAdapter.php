<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Adapter;

use PedhotDev\NepotismFree\Contract\IntrospectableContainerInterface;
use PedhotDev\NepotismFree\Debugger\Model\DebuggerGraph;
use PedhotDev\NepotismFree\Debugger\Model\DebuggerNode;

class IntrospectionAdapter
{
    public function __construct(
        private readonly ?IntrospectableContainerInterface $container = null
    ) {}

    public function getGraph(): DebuggerGraph
    {
        if ($this->container === null) {
            throw new \RuntimeException("No container loaded. Please specify a bootstrap file using --bootstrap|-b.");
        }

        $internalGraph = $this->container->getDependencyGraph();
        $internalNodes = $internalGraph->getNodes();
        
        $debuggerGraph = new DebuggerGraph();

        // 1. First pass: Create all nodes
        $registry = $this->extractRegistry();

        foreach ($internalNodes as $id => $node) {
            $scope = $registry ? $registry->getScope($id)->value : null;

            $debuggerNode = new DebuggerNode(
                id: $node->id,
                type: $node->type,
                isResolved: $node->isResolved,
                concrete: $node->concrete,
                scope: $scope,
                dependencies: $node->dependencies
            );
            $debuggerGraph->addNode($debuggerNode);
        }
        // 2. Second pass: Calculate parents (requiredBy)
        foreach ($internalNodes as $parentId => $node) {
            foreach ($node->dependencies as $childId) {
                // The container might report dependencies that are not in the graph? 
                // (e.g. optional deps or broken refs? Assume safe for now, but check validity)
                $childNode = $debuggerGraph->getNode($childId);
                if ($childNode) {
                    $childNode->addParent($parentId);
                }
            }
        }

        return $debuggerGraph;
    }

    private function extractRegistry(): ?\PedhotDev\NepotismFree\Core\Registry
    {
        if ($this->container instanceof \PedhotDev\NepotismFree\Core\Container) {
            $reflection = new \ReflectionClass(\PedhotDev\NepotismFree\Core\Container::class);
            if ($reflection->hasProperty('registry')) {
                $property = $reflection->getProperty('registry');
                return $property->getValue($this->container);
            }
        }

        return null;
    }

    public function getContainer(): IntrospectableContainerInterface
    {
        return $this->container;
    }
}
