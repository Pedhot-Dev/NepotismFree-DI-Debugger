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
return new Container($registry, $policy);
