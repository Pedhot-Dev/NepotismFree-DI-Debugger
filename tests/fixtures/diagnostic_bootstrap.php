<?php

use PedhotDev\NepotismFree\Core\Container;
use PedhotDev\NepotismFree\Core\Registry;
use PedhotDev\NepotismFree\Core\ModuleAccessPolicy;
use PedhotDev\NepotismFree\Contract\Scope;

// Define some classes for diagnostics
if (!class_exists('DiagnosticA')) {
    class DiagnosticA { public function __construct(DiagnosticB $b) {} }
    class DiagnosticB { public function __construct(DiagnosticA $a) {} }
    class ProcessService { public function __construct(TickService $t) {} }
    class TickService {}
    class OrphanService {}
    class RootService { public function __construct(ProcessService $p) {} }
}

$registry = new Registry();

// 1. Cycle: A -> B -> A
$registry->bind('DiagnosticA', 'DiagnosticA');
$registry->bind('DiagnosticB', 'DiagnosticB');

// 2. Scope Mismatch: ProcessService (PROCESS) -> TickService (TICK)
$registry->bind('ProcessService', 'ProcessService');
$registry->bind('TickService', 'TickService');
$registry->setScope('ProcessService', Scope::PROCESS);
$registry->setScope('TickService', Scope::TICK);

// 3. Orphan: OrphanService (not reachable from RootService)
$registry->bind('OrphanService', 'OrphanService');

// 4. Root: RootService -> ProcessService
$registry->bind('RootService', 'RootService');

$policy = new ModuleAccessPolicy($registry);

// Return an Introspectable container. 
// We use the real Container since it now implements the interface.
return new Container($registry, $policy);
