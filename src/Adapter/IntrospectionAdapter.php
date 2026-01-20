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
        foreach ($internalNodes as $id => $node) {
            $debuggerNode = new DebuggerNode(
                id: $node->id,
                type: $node->type,
                isResolved: $node->isResolved,
                concrete: $node->concrete,
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

    public function getContainer(): IntrospectableContainerInterface
    {
        return $this->container;
    }
}
