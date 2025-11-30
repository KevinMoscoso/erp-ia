<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2025 ERPIA Contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Core\Base;

use ERPIA\Core\Model\RoleAccess;
use ERPIA\Core\Model\User;

/**
 * Controller permissions management
 *
 * @author ERPIA Contributors
 */
final class ControllerPermissions
{
    /** @var int */
    public $accessLevel = 1;

    /** @var bool */
    public $allowAccess = false;

    /** @var bool */
    public $allowDelete = false;

    /** @var bool */
    public $allowExport = false;

    /** @var bool */
    public $allowImport = false;

    /** @var bool */
    public $allowUpdate = false;

    /** @var bool */
    public $restrictToOwnData = false;

    public function __construct(?User $user = null, ?string $controllerName = null)
    {
        if (empty($user) || empty($controllerName)) {
            return;
        }

        if ($user->isAdmin) {
            $this->setAdminPermissions();
        } else {
            $this->setUserPermissions($user, $controllerName);
        }
    }

    public function setPermissions(bool $access, int $level, bool $delete, bool $update, bool $ownData = false): void
    {
        $this->accessLevel = $level;
        $this->allowAccess = $access;
        $this->allowDelete = $delete;
        $this->allowUpdate = $update;
        $this->restrictToOwnData = $ownData;
    }

    public function applyParameters(array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }

            if ($key === 'accessLevel') {
                $this->{$key} = (int)$value;
            } else {
                $this->{$key} = (bool)$value;
            }
        }
    }

    private function setAdminPermissions(): void
    {
        $this->accessLevel = 99;
        $this->allowAccess = true;
        $this->allowDelete = true;
        $this->allowExport = true;
        $this->allowImport = true;
        $this->allowUpdate = true;
        $this->restrictToOwnData = false;
    }

    private function setUserPermissions(User $user, string $controllerName): void
    {
        $accessList = RoleAccess::getUserAccess($user->username, $controllerName);
        
        foreach ($accessList as $access) {
            $this->allowAccess = true;
            $this->allowDelete = $access->canDelete || $this->allowDelete;
            $this->allowExport = $access->canExport || $this->allowExport;
            $this->allowImport = $access->canImport || $this->allowImport;
            $this->allowUpdate = $access->canUpdate || $this->allowUpdate;
            $this->restrictToOwnData = $access->onlyOwnData || $this->restrictToOwnData;
        }
    }
}