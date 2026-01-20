<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Model;

/**
 * A bidirectional graph optimized for debugger queries.
 */
class DebuggerGraph
{
    /**
     * @param array<string, DebuggerNode> $nodes
     */
    public function __construct(
        private array $nodes = []
    ) {}

    public function addNode(DebuggerNode $node): void
    {
        $this->nodes[$node->id] = $node;
    }

    public function getNode(string $id): ?DebuggerNode
    {
        return $this->nodes[$id] ?? null;
    }

    /**
     * @return array<string, DebuggerNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }
}
