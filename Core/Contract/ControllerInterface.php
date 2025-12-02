<?php

namespace ERPIA\Core\Contract;

interface ControllerInterface
{
    public function __construct(string $className, string $url = '');

    public function getPageData(): array;

    public function run(): void;
}
