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

    protected function configure(): void
    {
        $this->addOption(
            'format',
            null,
            \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
            'Output format (text, json)',
            'text'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        if (!in_array($format, ['text', 'json'], true)) {
            $output->writeln(sprintf('<error>Invalid format "%s". Allowed: text, json</error>', $format));
            return Command::FAILURE;
        }

        $graph = $this->adapter->getGraph();
        
        // Collect Issues
        $cycles = GraphAnalyzer::findCycles($graph);
        $scopeViolations = GraphAnalyzer::validateScopes($graph);
        $missingDependencies = GraphAnalyzer::findMissingDependencies($graph);
        $orphans = GraphAnalyzer::findOrphans($graph);

        $hasStructuralErrors = !empty($cycles) || !empty($scopeViolations) || !empty($missingDependencies);
        $hasHygieneIssues = !empty($orphans);

        if ($format === 'json') {
            return $this->renderJson($output, $cycles, $scopeViolations, $missingDependencies, $orphans);
        }

        return $this->renderText($output, $cycles, $scopeViolations, $missingDependencies, $orphans);
    }

    private function renderText(OutputInterface $output, array $cycles, array $scopeViolations, array $missingDependencies, array $orphans): int
    {
        $output->writeln("Validating dependency graph...");
        $output->writeln("");

        $hasStructuralErrors = !empty($cycles) || !empty($scopeViolations) || !empty($missingDependencies);
        $hasHygieneIssues = !empty($orphans);

        // 1. Structural Errors (Fatal)
        if ($hasStructuralErrors) {
            $output->writeln("❌ <error>Structural Errors</error>");
            $output->writeln("<error>-----------------</error>");
            
            foreach ($cycles as $path) {
                $output->writeln(" - <error>Dependency cycle detected:</error>");
                $output->writeln("   " . implode(' -> ', $path));
            }

            foreach ($scopeViolations as $v) {
                $output->writeln(sprintf(
                    " - <error>Scope mismatch detected:</error>\n   <info>%s</info> service <comment>%s</comment> depends on <info>%s</info> service <comment>%s</comment>",
                    strtoupper($v['parentScope']),
                    $v['parent'],
                    strtoupper($v['childScope']),
                    $v['child']
                ));
            }

            foreach ($missingDependencies as $m) {
                $output->writeln(" - <error>Missing dependency detected:</error>");
                $output->writeln(sprintf("   <info>%s</info> -> <error>%s</error>", $m['from'], $m['missing']));
            }

            $output->writeln("");
        }

        // 2. Hygiene Issues (Non-fatal)
        if ($hasHygieneIssues) {
            $output->writeln("⚠️  <comment>Hygiene Issues</comment>");
            $output->writeln("<comment>--------------</comment>");
            
            if (!empty($orphans)) {
                $output->writeln(" - <comment>Orphan service(s) detected (unreachable from roots):</comment>");
                foreach ($orphans as $orphan) {
                    $output->writeln("   <info>{$orphan->id}</info>");
                }
            }
            $output->writeln("");
        }

        // 3. Final Verdict
        if ($hasStructuralErrors) {
            $output->writeln("❌ <error>Invalid dependency graph detected.</error>");
            $output->writeln("Please fix the structural errors above.");
            return Command::FAILURE;
        }

        if ($hasHygieneIssues) {
            $output->writeln("⚠️  <comment>Dependency graph is valid, but hygiene issues were found.</comment>");
            return Command::SUCCESS;
        }

        $output->writeln("✅ <info>Dependency graph is structurally sound.</info>");
        return Command::SUCCESS;
    }

    private function renderJson(OutputInterface $output, array $cycles, array $scopeViolations, array $missingDependencies, array $orphans): int
    {
        $hasStructuralErrors = !empty($cycles) || !empty($scopeViolations) || !empty($missingDependencies);

        $data = [
            'status' => $hasStructuralErrors ? 'invalid' : 'valid',
            'summary' => [
                'structural_errors' => count($cycles) + count($scopeViolations) + count($missingDependencies),
                'hygiene_issues' => count($orphans),
            ],
            'structural_errors' => [],
            'hygiene_issues' => [],
        ];

        foreach ($cycles as $path) {
            $data['structural_errors'][] = [
                'type' => 'dependency_cycle',
                'cycle' => $path,
            ];
        }

        foreach ($scopeViolations as $v) {
            $data['structural_errors'][] = [
                'type' => 'scope_mismatch',
                'from' => [
                    'service' => $v['parent'],
                    'scope' => strtoupper($v['parentScope']),
                ],
                'to' => [
                    'service' => $v['child'],
                    'scope' => strtoupper($v['childScope']),
                ],
            ];
        }

        foreach ($missingDependencies as $m) {
            $data['structural_errors'][] = [
                'type' => 'missing_dependency',
                'from' => $m['from'],
                'dependency' => $m['missing'],
            ];
        }

        foreach ($orphans as $orphan) {
            $data['hygiene_issues'][] = [
                'type' => 'orphan_service',
                'service' => $orphan->id,
                'reason' => 'unreachable_from_roots',
            ];
        }

        $output->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $hasStructuralErrors ? Command::FAILURE : Command::SUCCESS;
    }
}
