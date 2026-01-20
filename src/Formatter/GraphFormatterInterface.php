<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Debugger\Formatter;

use PedhotDev\NepotismFree\Debugger\Model\DebuggerGraph;

interface GraphFormatterInterface
{
    public function format(DebuggerGraph $graph): string;
}
