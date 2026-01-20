<?php

use PedhotDev\NepotismFree\Core\Container;
use PedhotDev\NepotismFree\Core\Registry;
use PedhotDev\NepotismFree\Core\ModuleAccessPolicy;
use Tests\Fixtures\Classes\ServiceA;
use Tests\Fixtures\Classes\ServiceB;
use Tests\Fixtures\Classes\ServiceC;
use Tests\Fixtures\Classes\RootService;

require_once __DIR__ . '/Classes/Services.php';

$registry = new Registry();

// Bindings
$registry->bind(ServiceC::class, ServiceC::class);
$registry->bind(ServiceB::class, ServiceB::class);
$registry->bind(ServiceA::class, ServiceA::class);
$registry->bind(RootService::class, RootService::class);

// Set singletons
$registry->setSingleton(ServiceC::class, true);
$registry->setSingleton(RootService::class, true);

// Access Policy
$policy = new ModuleAccessPolicy([]); // Allow everything for test

// Container
// Container
// For Debugger Smoke Test, we need an Introspectable container.
// The public library might not have it yet, so we mock it.
// And Polyfill the missing interface if needed.
require_once __DIR__ . '/Polyfill.php';
require_once __DIR__ . '/MockContainer.php';

return new \Tests\Fixtures\MockIntrospectableContainer($registry, $policy);
