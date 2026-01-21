<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Command;

use PedhotDev\NepotismFree\Debugger\Adapter\IntrospectionAdapter;
use PedhotDev\NepotismFree\Debugger\Model\DebuggerNode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'di:trace', description: 'Traces the resolution path of a service')]
class TraceCommand extends Command
{
    public function __construct(
        private readonly IntrospectionAdapter $adapter
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::REQUIRED, 'The Service ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $input->getArgument('service');
        $graph = $this->adapter->getGraph();
        $node = $graph->getNode($serviceId);

        if (!$node) {
            \PedhotDev\NepotismFree\Debugger\Util\SuggestionHelper::suggest($serviceId, array_keys($graph->getNodes()), $output);
            return Command::FAILURE;
        }

        $output->writeln("Resolution Trace for: <info>$serviceId</info>");
        $output->writeln("<comment>(Direction: dependencies required by this service)</comment>");
        $output->writeln("");

        $this->printTrace($node, $output, $graph, 0);

        if (empty($node->dependencies)) {
            $output->writeln("<comment>(no dependencies)</comment>");
        }

        return Command::SUCCESS;
    }

    private function printTrace(DebuggerNode $node, OutputInterface $output, $graph, int $depth, array $path = []): void
    {
        $indent = str_repeat('  ', $depth);
        $marker = $depth > 0 ? '-> ' : '';
        
        // Cycle detection in current path
        if (in_array($node->id, $path, true)) {
            $output->writeln(sprintf("%s%s<error>%s (Cycle Detected)</error>", $indent, $marker, $node->id));
            return;
        }

        $concreteInfo = $node->concrete ? " <comment>({$node->concrete})</comment>" : "";
        $status = $node->isResolved ? " [resolved]" : "";

        $output->writeln(sprintf("%s%s<info>%s</info>%s%s", $indent, $marker, $node->id, $concreteInfo, $status));

        $path[] = $node->id;
        
        foreach ($node->dependencies as $depId) {
            $depNode = $graph->getNode($depId);
            if ($depNode) {
                $this->printTrace($depNode, $output, $graph, $depth + 1, $path);
            } else {
                 $output->writeln(sprintf("%s  -> <error>%s (Missing Definition)</error>", $indent, $depId));
            }
        }
    }
}
