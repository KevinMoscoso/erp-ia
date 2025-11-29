<?php

namespace ERPIA\Core\Base\Contract;

interface MiniLogStorageInterface
{
    public function save(array $data): bool;
}
