<?php

namespace Tests\Fixtures\Classes;

class ServiceC {}

class ServiceB {
    public function __construct(public ServiceC $c) {}
}

class ServiceA {
    public function __construct(public ServiceB $b) {}
}

class RootService {
    public function __construct(public ServiceA $a) {}
}
