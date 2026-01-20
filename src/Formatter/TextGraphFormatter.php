<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Formatter;

use PedhotDev\NepotismFree\Debugger\Model\DebuggerGraph;
use PedhotDev\NepotismFree\Debugger\Model\DebuggerNode;

class TextGraphFormatter implements GraphFormatterInterface
{
    /**
     * @var array<string, bool> Tracks nodes visited in the global scope (across all trees)
     */
    private array $globalVisited = [];

    public function format(DebuggerGraph $graph): string
    {
        $this->globalVisited = []; // Reset per format call
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
        $output[] = "";
        
        if (!empty($roots)) {
            foreach ($roots as $root) {
                // ANCHOR: Entry point visual signal
                $output[] = sprintf("▶ ROOT: <info>%s</info>", $root->id);
                $output = array_merge($output, $this->renderTree($root, $graph));
                $output[] = ""; // Spacing
            }
        } else {
            $output[] = "<comment>No explicit entry points found.</comment>";
            $output[] = "";
        }

        // 2. Print any remaining nodes that weren't reached (e.g. disconnected cycles)
        $orphans = [];
        foreach ($nonRoots as $node) {
            if (!isset($this->globalVisited[$node->id])) {
                $orphans[] = $node;
            }
        }

        if (!empty($orphans)) {
            $output[] = "Disconnected / Cyclic Nodes:";
            foreach ($orphans as $orphan) {
                if (!isset($this->globalVisited[$orphan->id])) {
                     $output[] = sprintf("▶ ORPHAN: <comment>%s</comment>", $orphan->id);
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
    private function renderTree(DebuggerNode $node, DebuggerGraph $graph, string $currentPrefix = '', string $childrenPrefix = '', array $path = []): array
    {
        $lines = [];
        $isCycle = in_array($node->id, $path, true);
        
        $isShared = count($node->requiredBy) > 1;

        // Mark as visited globally
        $alreadyVisitedGlobal = isset($this->globalVisited[$node->id]);
        $this->globalVisited[$node->id] = true;

        // Label Formatting
        $concreteStr = '';
        if ($node->concrete && $node->concrete !== $node->id && $node->concrete !== 'closure') {
            $concreteStr = " <fg=gray>({$node->concrete})</>";
        }

        // Lifetime Symbol
        $symbol = match($node->type) {
            'singleton' => '<info>●</info>',
            'prototype' => '○',
            default => '?',
        };

        // Shared Tag
        $sharedTag = $isShared ? ' <fg=magenta>★ shared</>' : '';

        // Cycle Tag
        $cycleTag = $isCycle ? ' <error>(Cycle)</error>' : '';

        // Construct Label
        $label = sprintf("%s %s%s%s%s", $symbol, $node->id, $concreteStr, $sharedTag, $cycleTag);
        
        // Print the node line using the current prefix
        $lines[] = $currentPrefix . $label;

        // If cycle, stop
        if ($isCycle) {
            return $lines;
        }

        // Optimization for Shared Nodes: if visited globally, stop recursion to avoid noise
        if ($isShared && $alreadyVisitedGlobal) {
            return $lines;
        }

        // Dependencies
        $children = $node->dependencies;
        $count = count($children);
        
        if ($count === 0) {
            return $lines;
        }

        $path[] = $node->id;

        foreach ($children as $index => $childId) {
            $isLast = ($index === $count - 1);
            $connector = $isLast ? '└─ ' : '├─ ';
            $childParams = $isLast ? '   ' : '│  ';
            
            $childNode = $graph->getNode($childId);
            
            if (!$childNode) {
                $lines[] = $childrenPrefix . $connector . "<error>$childId (Missing)</error>";
            } else {
                $lines = array_merge(
                    $lines, 
                    $this->renderTree(
                        $childNode, 
                        $graph, 
                        $childrenPrefix . $connector, 
                        $childrenPrefix . $childParams, 
                        $path
                    )
                );
            }
        }
        
        return $lines;
    }
    
    // Correction on recursion logic to match standard "tree" command output style:
    // renderTree is responsible for printing the node lines.
    // Issue with previous logic:
    // $lines[] = $prefix . $label; 
    // And in loop: renderTree(child, $prefix . $subPrefix)
    
    // When called from root: prefix is empty. 
    // root -> "Label"
    // loop child A (not last): connector '├─ ', subPrefix '│  '
    // recurse(A, '│  '). 
    // A -> "│  Label" -> Wait, where depends the connector?
    
    // Ah, the connector belongs to the PARENT calling the child.
    // The previous code had: $lines[] = $prefix . $connector . childLabel... 
    // But now I am calling renderTree recursively which starts with $lines[] = $prefix . $label.
    
    // So the $prefix passed to renderTree MUST include the connector for the *root* of that subtree?
    // No, usually you pass the prefix for *children* and handle the connector locally.
    
    // Method 2 (Cleaner recursion):
    // renderTree(node, prefixForSelf, prefixForChildren)
    // Root call: renderTree(root, "", "")
    
    // Let's try to stick closer to the logic that works.
    // Refactoring to a private helper that knows about connectors?
    
    // Let's keep it simple:
    // renderTree($node, $currentPrefix) 
    // This function prints the node using $currentPrefix.
    // PROBLEM: $currentPrefix includes the └─ or ├─ which is only for the first line.
    
    // Solution:
    // renderNode($node, $prefixFirstLine, $prefixChildren, $path)
}
