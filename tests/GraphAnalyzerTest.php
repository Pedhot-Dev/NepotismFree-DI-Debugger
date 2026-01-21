<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Tests;

use PHPUnit\Framework\TestCase;
use PedhotDev\NepotismFree\Debugger\Model\DebuggerGraph;
use PedhotDev\NepotismFree\Debugger\Model\DebuggerNode;
use PedhotDev\NepotismFree\Debugger\Util\GraphAnalyzer;

/**
 * @covers \PedhotDev\NepotismFree\Debugger\Util\GraphAnalyzer
 */
class GraphAnalyzerTest extends TestCase
{
    private DebuggerGraph $graph;

    protected function setUp(): void
    {
        $this->graph = new DebuggerGraph();
    }

    public function testFindRoots(): void
    {
        $nodeA = new DebuggerNode('A', 'singleton', true, 'ClassA');
        $nodeB = new DebuggerNode('B', 'singleton', true, 'ClassB', dependencies: ['A']);
        
        $this->graph->addNode($nodeA);
        $this->graph->addNode($nodeB);
        
        // Manual parent calculation as IntrospectionAdapter would do
        $nodeA->addParent('B');

        $roots = GraphAnalyzer::findRoots($this->graph);
        
        $this->assertCount(1, $roots);
        $this->assertEquals('B', $roots[0]->id);
    }

    public function testFindOrphans(): void
    {
        $nodeRoot = new DebuggerNode('Root', 'singleton', true, 'RootClass');
        $nodeOrphanA = new DebuggerNode('OrphanA', 'singleton', true, 'OrphanA', dependencies: ['OrphanB']);
        $nodeOrphanB = new DebuggerNode('OrphanB', 'singleton', true, 'OrphanB', dependencies: ['OrphanA']);
        
        $nodeOrphanA->addParent('OrphanB');
        $nodeOrphanB->addParent('OrphanA');

        $this->graph->addNode($nodeRoot);
        $this->graph->addNode($nodeOrphanA);
        $this->graph->addNode($nodeOrphanB);

        $orphans = GraphAnalyzer::findOrphans($this->graph);
        
        $this->assertCount(2, $orphans);
    }

    public function testFindCycles(): void
    {
        $nodeA = new DebuggerNode('A', 'singleton', true, 'ClassA', dependencies: ['B']);
        $nodeB = new DebuggerNode('B', 'singleton', true, 'ClassB', dependencies: ['A']);
        
        $this->graph->addNode($nodeA);
        $this->graph->addNode($nodeB);

        $cycles = GraphAnalyzer::findCycles($this->graph);
        
        $this->assertCount(1, $cycles); // Detected once during traversal
        $this->assertContains('A', $cycles['A']);
        $this->assertContains('B', $cycles['A']);
    }

    public function testValidateScopes(): void
    {
        $process = new DebuggerNode('Process', 'singleton', true, 'P', scope: 'process', dependencies: ['Tick']);
        $tick = new DebuggerNode('Tick', 'singleton', true, 'T', scope: 'tick');
        
        $this->graph->addNode($process);
        $this->graph->addNode($tick);

        $violations = GraphAnalyzer::validateScopes($this->graph);
        
        $this->assertCount(1, $violations);
        $this->assertEquals('Process', $violations[0]['parent']);
        $this->assertEquals('Tick', $violations[0]['child']);
    }

    public function testFindMissingDependencies(): void
    {
        $nodeA = new DebuggerNode('A', 'singleton', true, 'ClassA', dependencies: ['B']);
        $this->graph->addNode($nodeA);

        $missing = GraphAnalyzer::findMissingDependencies($this->graph);

        $this->assertCount(1, $missing);
        $this->assertEquals('A', $missing[0]['from']);
        $this->assertEquals('B', $missing[0]['missing']);
    }
}
