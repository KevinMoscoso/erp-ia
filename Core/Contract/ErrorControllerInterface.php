<?php

namespace ERPIA\Core\Contract;

use Exception;

interface ErrorControllerInterface
{
    public function __construct(Exception $exception, string $url = '');

    public function run(): void;
}
