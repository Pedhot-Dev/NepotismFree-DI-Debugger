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

#[AsCommand(name: 'di:why', description: 'Explains why a service is instantiated')]
class WhyCommand extends Command
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
            $output->writeln("<error>Service '$serviceId' not found in the graph.</error>");
            return Command::FAILURE;
        }

        $output->writeln(sprintf("Service: <info>%s</info>", $node->id));
        $output->writeln(sprintf("Type: <comment>%s</comment>", $node->type));
        $output->writeln(sprintf("Resolved: %s", $node->isResolved ? 'Yes' : 'No'));
        if ($node->concrete) {
            $output->writeln(sprintf("Concrete: %s", $node->concrete));
        }

        $output->writeln("");
        
        if (empty($node->requiredBy)) {
            $output->writeln("This service is not required by any other service (It is a root or unused).");
        } else {
            $output->writeln("<comment>Required by:</comment>");
            $this->printParents($node, $output, $graph, 1);
        }

        return Command::SUCCESS;
    }

    private function printParents(DebuggerNode $node, OutputInterface $output, $graph, int $depth, array $path = []): void
    {
        if ($depth > 5) {
            $output->writeln(str_repeat('  ', $depth) . "...");
            return;
        }

        foreach ($node->requiredBy as $parentId) {
            if (in_array($parentId, $path, true)) {
                 $output->writeln(str_repeat('  ', $depth) . "- <error>$parentId (Cycle)</error>");
                 continue;
            }

            $output->writeln(str_repeat('  ', $depth) . "- <info>$parentId</info>");
            
            $parentNode = $graph->getNode($parentId);
            if ($parentNode) {
                // Look further up
                $newPath = $path;
                $newPath[] = $parentId;
                // Only print if parent has parents, otherwise it's a root of this branch
                if (!empty($parentNode->requiredBy)) {
                     $this->printParents($parentNode, $output, $graph, $depth + 1, $newPath);
                }
            }
        }
    }
}
