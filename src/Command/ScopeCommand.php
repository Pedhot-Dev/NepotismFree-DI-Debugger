<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Command;

use PedhotDev\NepotismFree\Debugger\Adapter\IntrospectionAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'di:scope', description: 'Inspects services grouped by scope')]
class ScopeCommand extends Command
{
    public function __construct(
        private readonly IntrospectionAdapter $adapter
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('scope', InputArgument::OPTIONAL, 'Specific scope to list (process, tick, prototype)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetScope = $input->getArgument('scope');
        if ($targetScope) {
            $targetScope = strtolower($targetScope);
        }

        $graph = $this->adapter->getGraph();
        $grouped = [];

        foreach ($graph->getNodes() as $node) {
            $scope = $node->scope ?? 'unknown';
            $grouped[$scope][] = $node->id;
        }

        if ($targetScope) {
            if (!isset($grouped[$targetScope])) {
                $output->writeln("No services found for scope: <info>$targetScope</info>");
                return Command::SUCCESS;
            }
            $this->printScope($targetScope, $grouped[$targetScope], $output);
            return Command::SUCCESS;
        }

        foreach ($grouped as $scope => $services) {
            $this->printScope($scope, $services, $output);
            $output->writeln("");
        }

        return Command::SUCCESS;
    }

    private function printScope(string $scope, array $services, OutputInterface $output): void
    {
        $output->writeln(sprintf("<comment>%s scoped services:</comment>", strtoupper($scope)));
        foreach ($services as $service) {
            $output->writeln(" - <info>$service</info>");
        }
    }
}
