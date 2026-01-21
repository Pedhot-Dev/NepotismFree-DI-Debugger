<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Command;

use PedhotDev\NepotismFree\Debugger\Adapter\IntrospectionAdapter;
use PedhotDev\NepotismFree\Debugger\Util\GraphAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'di:validate', description: 'Validates the dependency graph for structural and semantic correctness')]
class ValidateCommand extends Command
{
    public function __construct(
        private readonly IntrospectionAdapter $adapter
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $graph = $this->adapter->getGraph();
        $isValid = true;

        $output->writeln("Validating dependency graph...");
        $output->writeln("");

        // 1. Cycles
        $cycles = GraphAnalyzer::findCycles($graph);
        if (!empty($cycles)) {
            $isValid = false;
            $output->writeln("❌ <error>Dependency cycle(s) detected:</error>");
            foreach ($cycles as $startNode => $path) {
                $output->writeln(" - " . implode(' -> ', $path));
            }
            $output->writeln("");
        }

        // 2. Scope Mismatches
        $violations = GraphAnalyzer::validateScopes($graph);
        if (!empty($violations)) {
            $isValid = false;
            $output->writeln("❌ <error>Scope mismatch(es) detected:</error>");
            foreach ($violations as $v) {
                $output->writeln(sprintf(
                    " - <info>%s</info> scoped service <comment>%s</comment> depends on <info>%s</info> service <comment>%s</comment>",
                    strtoupper($v['parentScope']),
                    $v['parent'],
                    strtoupper($v['childScope']),
                    $v['child']
                ));
            }
            $output->writeln("");
        }

        // 3. Orphans
        $orphans = GraphAnalyzer::findOrphans($graph);
        if (!empty($orphans)) {
            // Note: Orphans aren't necessarily "invalid" in a hard sense, 
            // but they are a maintenance smell. We report them.
            $output->writeln("⚠️  <comment>Orphan service(s) detected (unreachable from roots):</comment>");
            foreach ($orphans as $orphan) {
                $output->writeln(" - <info>{$orphan->id}</info>");
            }
            $output->writeln("");
        }

        if ($isValid) {
            $output->writeln("✅ <info>Dependency graph is structurally sound.</info>");
            return Command::SUCCESS;
        }

        $output->writeln("<error>Invalid dependency graph detected. Please fix the issues above.</error>");
        return Command::FAILURE;
    }
}
