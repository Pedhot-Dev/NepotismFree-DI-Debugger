<?php

namespace PedhotDev\NepotismFree\Contract;

if (!interface_exists(IntrospectableContainerInterface::class)) {
    interface IntrospectableContainerInterface extends ContainerInterface
    {
        public function getDependencyGraph(): mixed;
    }
}
