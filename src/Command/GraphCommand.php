<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Command;

use PedhotDev\NepotismFree\Debugger\Adapter\IntrospectionAdapter;
use PedhotDev\NepotismFree\Debugger\Formatter\DotGraphFormatter;
use PedhotDev\NepotismFree\Debugger\Formatter\MermaidGraphFormatter;
use PedhotDev\NepotismFree\Debugger\Formatter\TextGraphFormatter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'di:graph', description: 'Outputs the dependency graph')]
class GraphCommand extends Command
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
            'f',
            InputOption::VALUE_REQUIRED,
            'Output format (text, mermaid, dot)',
            'text'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        $graph = $this->adapter->getGraph();

        $formatter = match ($format) {
            'mermaid' => new MermaidGraphFormatter(),
            'dot' => new DotGraphFormatter(),
            'text' => new TextGraphFormatter(),
            default => throw new \InvalidArgumentException("Invalid format: $format"),
        };

        $result = $formatter->format($graph);
        $output->writeln($result);

        return Command::SUCCESS;
    }
}
