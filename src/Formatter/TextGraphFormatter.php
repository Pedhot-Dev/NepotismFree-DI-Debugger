<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Formatter;

use PedhotDev\NepotismFree\Debugger\Model\DebuggerGraph;
use PedhotDev\NepotismFree\Debugger\Model\DebuggerNode;

class TextGraphFormatter implements GraphFormatterInterface
{
    private array $visited = [];

    public function format(DebuggerGraph $graph): string
    {
        $this->visited = [];
        $nodes = $graph->getNodes();
        
        // 1. Identify Roots (Entry Points)
        $roots = [];
        $nonRoots = [];

        foreach ($nodes as $node) {
            if (empty($node->requiredBy)) {
                $roots[] = $node;
            } else {
                $nonRoots[$node->id] = $node;
            }
        }

        // Sort for stability
        usort($roots, fn($a, $b) => $a->id <=> $b->id);
        ksort($nonRoots);

        $output = [];
        $output[] = "Runtime Dependency Graph";
        
        if (!empty($roots)) {
            $output[] = "Entry point(s): " . implode(', ', array_map(fn($n) => $n->id, $roots));
            $output[] = "";

            foreach ($roots as $root) {
                $output = array_merge($output, $this->renderTree($root, $graph));
                $output[] = ""; // Spacing
            }
        } else {
            $output[] = "<comment>No explicit entry points found (all nodes are circular or required).</comment>";
            $output[] = "";
        }

        // 2. Print any remaining nodes that weren't reached (e.g. disconnected cycles)
        $orphans = [];
        foreach ($nonRoots as $node) {
            if (!isset($this->visited[$node->id])) {
                $orphans[] = $node;
            }
        }

        if (!empty($orphans)) {
            $output[] = "Disconnected / Cyclic Nodes:";
            foreach ($orphans as $orphan) {
                // Determine if this is a 'local root' of a cycle
                // We just render it as a tree root for visibility
                if (!isset($this->visited[$orphan->id])) {
                    $output = array_merge($output, $this->renderTree($orphan, $graph));
                    $output[] = "";
                }
            }
        }

        return implode("\n", $output);
    }

    /**
     * @return string[]
     */
    private function renderTree(DebuggerNode $node, DebuggerGraph $graph, string $prefix = '', array $path = []): array
    {
        $lines = [];
        $isCycle = in_array($node->id, $path, true);
        $this->visited[$node->id] = true;

        // Label Formatting
        // Collapse redundant info: If ID matches Concrete, hide concrete
        $concreteStr = '';
        if ($node->concrete && $node->concrete !== $node->id && $node->concrete !== 'closure') {
            $concreteStr = " <comment>({$node->concrete})</comment>";
        }

        $typeColor = match($node->type) {
            'singleton' => 'info',
            'prototype' => 'comment',
            default => 'fg=white'
        };

        $label = sprintf(
            "[%s] <%s>(%s)</%s>%s", 
            $node->id, 
            $typeColor, 
            $node->type, 
            $typeColor, 
            $concreteStr
        );
        
        if ($isCycle) {
            $lines[] = $prefix . $label . " <error>(Cycle)</error>";
            return $lines;
        }

        $lines[] = $prefix . $label;

        // Dependencies
        $children = $node->dependencies;
        $count = count($children);
        
        if ($count === 0) {
            return $lines;
        }

        // Add "depends on:" label logic if we want to match the user's example strictly? 
        // User example: "  └─ depends on:" ... "    └─ ServiceA".
        // That adds extra vertical space. Let's stick to direct tree for compactness unless requested explicitly?
        // User said "Target output style (example, not exact)".
        // Cleaner:
        // [Root]
        //   └─ ChildA
        //   └─ ChildB
        
        // HOWEVER, user's example has explicit "depends on". 
        // Let's adopt a compact tree style directly.
        
        $path[] = $node->id;

        foreach ($children as $index => $childId) {
            $isLast = ($index === $count - 1);
            $connector = $isLast ? '└─ ' : '├─ ';
            $subPrefix = $isLast ? '   ' : '│  ';
            
            $childNode = $graph->getNode($childId);
            
            if (!$childNode) {
                $lines[] = $prefix . $connector . "<error>$childId (Missing)</error>";
            } else {
                // Check if already visited in GLOBAL scope? 
                // We want to avoid printing huge subtrees multiple times (Shared Dependencies).
                // BUT we want to show that it exists.
                // Strategy: If already visited and is a complex node (has kids), maybe summarize?
                // Or just print usage?
                
                // If it's a Singleton and already visited, often good to stop and say "See above".
                // If Prototype, technically it's a new instance, but graph-wise it's the same structure.
                
                // Let's implement: If node has dependencies AND was already visited globally, 
                // print it but don't recurse.
                // UNLESS it was visited in the *current* path (recursion loop handled above).
                 
                // Actually, let's allow it for now, but if graph is huge...
                // The requirements say "Output must remain readable for large graphs".
                // Printing a shared dependency tree 50 times IS unreadable.
                
                // Improvement: If visited globally, print node label + "..." or similar.
                
                // Current path check is for Cycles.
                // Global visited check is for Conciseness.
                
                // But we marked "visited" at top of function.
                // So recursive calls will see it as visited. We need to distinguish current path vs global.
                // I used $this->visited for global.
                
                // Wait, if I use global visited, I might skip rendering a child if it was rendered elsewhere?
                // Yes.
                // Example: A->C, B->C.
                // Render A. Renders C. C marked visited.
                // Render B. Sees C. C visited. Should I render C?
                // Yes, I should show B->C. But should I expand C's children?
                // If C is expanded under A, repeating it under B is noise.
                // So: Print C (label), but Stop recursion. adding "(*)" or similar.
                
                $isGloballyVisited = isset($this->visited[$childId]) && !in_array($childId, $path, true);
                
                if ($isGloballyVisited) {
                     // Get child label logic quickly
                    $cTypeColor = match($childNode->type) { 'singleton' => 'info', default => 'comment' };
                    $cConcreteStr = ($childNode->concrete && $childNode->concrete !== $childId && $childNode->concrete !== 'closure') ? " ($childNode->concrete)" : "";
                    
                    $lines[] = $prefix . $connector . sprintf("[%s] <%s>(%s)</%s>%s ...", $childId, $cTypeColor, $childNode->type, $cTypeColor, $cConcreteStr);
                } else {
                     $lines = array_merge($lines, $this->renderTree($childNode, $graph, $prefix . $subPrefix, $path));
                }
            }
        }
        
        return $lines;
    }
}
