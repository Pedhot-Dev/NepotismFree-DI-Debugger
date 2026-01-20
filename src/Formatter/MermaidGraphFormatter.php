<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Formatter;

use PedhotDev\NepotismFree\Debugger\Model\DebuggerGraph;

class MermaidGraphFormatter implements GraphFormatterInterface
{
    public function format(DebuggerGraph $graph): string
    {
        $lines = ["graph TD"];
        
        foreach ($graph->getNodes() as $node) {
            // Sanitize ID for mermaid
            $safeId = $this->escapeId($node->id);
            $label = $node->id;
            
            // Add node definition with style
            // resolved nodes could be green, etc.
            // keeping it simple for now.
            $lines[] = sprintf('    %s["%s\n(%s)"]', $safeId, $label, $node->type);

            foreach ($node->dependencies as $depId) {
                $safeDepId = $this->escapeId($depId);
                $lines[] = sprintf('    %s --> %s', $safeId, $safeDepId);
            }
        }

        return implode("\n", $lines);
    }

    private function escapeId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9]/', '_', $id);
    }
}
