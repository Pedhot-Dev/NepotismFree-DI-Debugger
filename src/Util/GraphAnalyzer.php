<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Util;

use PedhotDev\NepotismFree\Debugger\Model\DebuggerGraph;
use PedhotDev\NepotismFree\Debugger\Model\DebuggerNode;

class GraphAnalyzer
{
    /**
     * @return DebuggerNode[]
     */
    public static function findRoots(DebuggerGraph $graph): array
    {
        $roots = [];
        foreach ($graph->getNodes() as $node) {
            if (empty($node->requiredBy)) {
                $roots[] = $node;
            }
        }
        return $roots;
    }

    /**
     * @return DebuggerNode[]
     */
    public static function findOrphans(DebuggerGraph $graph): array
    {
        $roots = self::findRoots($graph);
        $reachable = [];
        
        foreach ($roots as $root) {
            self::markReachable($root, $graph, $reachable);
        }

        $orphans = [];
        foreach ($graph->getNodes() as $id => $node) {
            if (!isset($reachable[$id])) {
                $orphans[] = $node;
            }
        }

        return $orphans;
    }

    private static function markReachable(DebuggerNode $node, DebuggerGraph $graph, array &$reachable): void
    {
        if (isset($reachable[$node->id])) {
            return;
        }

        $reachable[$node->id] = true;

        foreach ($node->dependencies as $depId) {
            $depNode = $graph->getNode($depId);
            if ($depNode) {
                self::markReachable($depNode, $graph, $reachable);
            }
        }
    }

    /**
     * @return array<string, string[]> map of service ID to dependency path forming a cycle
     */
    public static function findCycles(DebuggerGraph $graph): array
    {
        $cycles = [];
        $visited = [];
        $stack = [];

        foreach ($graph->getNodes() as $node) {
            self::detectCycles($node, $graph, $visited, $stack, $cycles);
        }

        return $cycles;
    }

    private static function detectCycles(DebuggerNode $node, DebuggerGraph $graph, array &$visited, array $stack, array &$cycles): void
    {
        if (in_array($node->id, $stack, true)) {
            $cycle = array_slice($stack, array_search($node->id, $stack, true));
            $cycle[] = $node->id;
            $cycles[$node->id] = $cycle;
            return;
        }

        if (isset($visited[$node->id])) {
            return;
        }

        $visited[$node->id] = true;
        $stack[] = $node->id;

        foreach ($node->dependencies as $depId) {
            $depNode = $graph->getNode($depId);
            if ($depNode) {
                self::detectCycles($depNode, $graph, $visited, $stack, $cycles);
            }
        }
    }

    /**
     * @return array<string, array{parent: string, child: string, parentScope: string, childScope: string}>
     */
    public static function validateScopes(DebuggerGraph $graph): array
    {
        $violations = [];
        foreach ($graph->getNodes() as $node) {
            foreach ($node->dependencies as $depId) {
                $depNode = $graph->getNode($depId);
                if (!$depNode || !$node->scope || !$depNode->scope) {
                    continue;
                }

                if ($node->scope === 'process' && $depNode->scope === 'tick') {
                    $violations[] = [
                        'parent' => $node->id,
                        'child' => $depNode->id,
                        'parentScope' => $node->scope,
                        'childScope' => $depNode->scope,
                    ];
                }
            }
        }
        return $violations;
    }

    /**
     * @return array<int, array{from: string, missing: string}>
     */
    public static function findMissingDependencies(DebuggerGraph $graph): array
    {
        $missing = [];
        foreach ($graph->getNodes() as $node) {
            foreach ($node->dependencies as $depId) {
                if (!$graph->getNode($depId)) {
                    $missing[] = [
                        'from' => $node->id,
                        'missing' => $depId,
                    ];
                }
            }
        }
        return $missing;
    }
}
