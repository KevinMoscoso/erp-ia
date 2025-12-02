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

use ERPIA\Core\Internal\PluginsDeploy;

/**
 * Plugin deployment management facade
 *
 * @author ERPIA Contributors
 */
final class PluginDeploy
{
    /**
     * Deploy enabled plugins
     */
    public function deploy(string $pluginPath, array $enabledPlugins, bool $clean = true): void
    {
        PluginsDeploy::run($enabledPlugins, $clean);
    }

    /**
     * Initialize controllers (placeholder for compatibility)
     */
    public function initControllers(): void
    {
        // Kept for backward compatibility
    }
}