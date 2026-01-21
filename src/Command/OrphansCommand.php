<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Command;

use PedhotDev\NepotismFree\Debugger\Adapter\IntrospectionAdapter;
use PedhotDev\NepotismFree\Debugger\Util\GraphAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'di:orphans', description: 'Detects unused / dead services (orphans)')]
class OrphansCommand extends Command
{
    public function __construct(
        private readonly IntrospectionAdapter $adapter
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $graph = $this->adapter->getGraph();
        $orphans = GraphAnalyzer::findOrphans($graph);

        if (empty($orphans)) {
            $output->writeln("<info>No orphan services detected. Your graph is clean!</info>");
            return Command::SUCCESS;
        }

        $output->writeln("<error>Unused services detected:</error>");
        foreach ($orphans as $orphan) {
            $output->writeln(" - <info>{$orphan->id}</info>");
        }

        return Command::SUCCESS;
    }
}
