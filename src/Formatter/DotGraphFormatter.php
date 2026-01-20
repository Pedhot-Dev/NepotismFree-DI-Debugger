<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Formatter;

use PedhotDev\NepotismFree\Debugger\Model\DebuggerGraph;

class DotGraphFormatter implements GraphFormatterInterface
{
    public function format(DebuggerGraph $graph): string
    {
        $lines = ["digraph Container {"];
        $lines[] = "    node [shape=box, style=filled, fillcolor=lightgrey];";
        
        foreach ($graph->getNodes() as $node) {
            $safeId = '"' . $node->id . '"';
            
            // Node attributes
            $attrs = [];
            if ($node->type === 'singleton') {
                $attrs[] = 'color=blue';
            }
            if ($node->isResolved) {
                $attrs[] = 'fillcolor=green';
            }
            
            $attrStr = $attrs ? '[' . implode(',', $attrs) . ']' : '';
            $lines[] = sprintf('    %s %s;', $safeId, $attrStr);

            foreach ($node->dependencies as $depId) {
                $safeDepId = '"' . $depId . '"';
                $lines[] = sprintf('    %s -> %s;', $safeId, $safeDepId);
            }
        }

        $lines[] = "}";
        return implode("\n", $lines);
    }
}
