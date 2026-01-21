<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Util;

use Symfony\Component\Console\Output\OutputInterface;

class SuggestionHelper
{
    /**
     * @param string[] $allServiceIds
     */
    public static function suggest(string $inputId, array $allServiceIds, OutputInterface $output): void
    {
        $output->writeln("<error>Service '$inputId' not found.</error>");

        $bestMatches = [];
        foreach ($allServiceIds as $id) {
            $distance = levenshtein($inputId, $id);
            if ($distance <= 3) {
                $bestMatches[$id] = $distance;
            }
        }

        asort($bestMatches);
        $bestMatches = array_slice($bestMatches, 0, 3, true);

        if (!empty($bestMatches)) {
            $output->writeln("");
            $output->writeln("Did you mean:");
            foreach (array_keys($bestMatches) as $match) {
                $output->writeln("  - <info>$match</info>");
            }
        }

        $output->writeln("");
        $output->writeln("Hint:");
        $output->writeln("  If the service name contains backslashes, wrap it in quotes:");
        $output->writeln("    <comment>di:trace '$inputId'</comment>");
    }
}
