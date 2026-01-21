<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Command;

use PedhotDev\NepotismFree\Debugger\Adapter\IntrospectionAdapter;
use PedhotDev\NepotismFree\Debugger\Util\GraphAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'di:roots', description: 'Lists all entry-point services (roots)')]
class RootsCommand extends Command
{
    public function __construct(
        private readonly IntrospectionAdapter $adapter
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $graph = $this->adapter->getGraph();
        $roots = GraphAnalyzer::findRoots($graph);

        if (empty($roots)) {
            $output->writeln("No root services found.");
            return Command::SUCCESS;
        }

        $output->writeln("<comment>Root services:</comment>");
        foreach ($roots as $root) {
            $output->writeln(" - <info>{$root->id}</info>");
        }

        return Command::SUCCESS;
    }
}
