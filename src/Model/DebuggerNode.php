<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Model;

/**
 * Enhanced node model for the debugger.
 * Supports bidirectional traversal (parents and children).
 */
class DebuggerNode
{
    /**
     * @param string[] $dependencies IDs of services this node depends on (children)
     * @param string[] $requiredBy IDs of services that depend on this node (parents)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type, // singleton, factory, etc
        public readonly bool $isResolved,
        public readonly ?string $concrete,
        public array $dependencies = [],
        public array $requiredBy = [],
    ) {}

    public function addParent(string $parentId): void
    {
        if (!in_array($parentId, $this->requiredBy, true)) {
            $this->requiredBy[] = $parentId;
        }
    }
}
