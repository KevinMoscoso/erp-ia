<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2025 ERPIA Development Team
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

namespace ERPIA\Core\Internal;

use ERPIA\Core\Http;
use ERPIA\Core\Kernel;
use ERPIA\Core\Tools;

final class Forja
{
    const DOWNLOAD_URL = 'https://erpia.com/DownloadBuild';
    const CORE_ID = 1;
    const PLUGIN_URL = 'https://erpia.com/PluginInfoList';

    /** @var array */
    private static $availableBuilds;

    /** @var array */
    private static $availablePlugins;

    public static function builds(): array
    {
        if (!isset(self::$availableBuilds)) {
            self::$availableBuilds = Http::get(self::DOWNLOAD_URL)->setTimeout(10)->json() ?? [];
        }

        return self::$availableBuilds ?? [];
    }

    public static function checkCoreUpdate(): bool
    {
        $currentVersion = Kernel::version();
        
        foreach (self::fetchBuilds(self::CORE_ID) as $buildItem) {
            if ($buildItem['stable'] && version_compare($buildItem['version'], $currentVersion, '>')) {
                return true;
            }

            $betaEnabled = Tools::config('default', 'beta_updates', false);
            if ($betaEnabled && $buildItem['beta'] && version_compare($buildItem['version'], $currentVersion, '>')) {
                return true;
            }
        }

        return false;
    }

    public static function fetchBuilds(int $projectId): array
    {
        foreach (self::builds() as $projectData) {
            if ($projectData['project'] == $projectId) {
                return $projectData['builds'];
            }
        }

        return [];
    }

    public static function fetchBuildsByName(string $pluginName): array
    {
        foreach (self::builds() as $projectData) {
            if ($projectData['name'] == $pluginName) {
                return $projectData['builds'];
            }
        }

        return [];
    }

    public static function plugins(): array
    {
        if (!isset(self::$availablePlugins)) {
            self::$availablePlugins = Http::get(self::PLUGIN_URL)->setTimeout(10)->json() ?? [];
        }

        return self::$availablePlugins ?? [];
    }

    public static function canUpdateCore(): bool
    {
        return self::checkCoreUpdate();
    }

    public static function getBuilds(int $id): array
    {
        return self::fetchBuilds($id);
    }

    public static function getBuildsByName(string $pluginName): array
    {
        return self::fetchBuildsByName($pluginName);
    }
}