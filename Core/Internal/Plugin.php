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

use ERPIA\Core\Kernel;
use ERPIA\Core\Plugins;
use ERPIA\Core\Tools;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

final class Plugin
{
    /** @var bool */
    public $compatible = false;

    /** @var string */
    private $compatibilityMessage = '';

    /** @var string */
    public $description = 'unknown';

    /** @var bool */
    public $enabled = false;

    /** @var string */
    public $folder = '-';

    /** @var bool */
    public $hidden = false;

    /** @var bool */
    public $installed = false;

    /** @var float */
    public $min_version = 0;

    /** @var float */
    public $min_php = 8;

    /** @var string */
    public $name = '-';

    /** @var int */
    public $order = 0;

    /** @var bool */
    public $post_disable = false;

    /** @var bool */
    public $post_enable = false;

    /** @var array */
    public $require = [];

    /** @var array */
    public $require_php = [];

    /** @var float */
    public $version = 0.0;

    public function __construct(array $data = [])
    {
        $this->enabled = $data['enabled'] ?? false;
        $this->folder = $data['folder'] ?? $data['name'] ?? '-';
        $this->name = $data['name'] ?? '-';
        $this->order = intval($data['order'] ?? 0);
        $this->post_disable = $data['post_disable'] ?? false;
        $this->post_enable = $data['post_enable'] ?? false;

        $this->loadConfiguration();
    }

    public function compatibilityDescription(): string
    {
        return $this->compatibilityMessage;
    }

    public function delete(): bool
    {
        if (!file_exists($this->getFolderPath())) {
            return true;
        }

        $directory = new RecursiveDirectoryIterator($this->getFolderPath(), FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::CHILD_FIRST);
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        return rmdir($this->getFolderPath());
    }

    public function dependenciesOk(array $activePlugins, bool $showErrors = false): bool
    {
        if (!$this->compatible) {
            return false;
        }

        foreach ($this->require as $requiredPlugin) {
            if (in_array($requiredPlugin, $activePlugins)) {
                continue;
            }
            if ($showErrors) {
                Tools::log()->warning('plugin-required', ['%plugin%' => $requiredPlugin]);
            }
            return false;
        }

        foreach ($this->require_php as $phpExtension) {
            if (extension_loaded($phpExtension)) {
                continue;
            }
            if ($showErrors) {
                Tools::log()->warning('php-extension-required', ['%extension%' => $phpExtension]);
            }
            return false;
        }

        return true;
    }

    public function disabled(): bool
    {
        return !$this->enabled;
    }

    public function exists(): bool
    {
        return file_exists($this->getFolderPath());
    }

    public function getFolderPath(): string
    {
        return Plugins::getPluginsDirectory() . DIRECTORY_SEPARATOR . $this->name;
    }

    public function forja(string $field, $default)
    {
        foreach (Forja::plugins() as $pluginInfo) {
            if ($pluginInfo['name'] === $this->name) {
                return $pluginInfo[$field] ?? $default;
            }
        }

        foreach (Forja::builds() as $buildInfo) {
            if ($buildInfo['name'] === $this->name) {
                return $buildInfo[$field] ?? $default;
            }
        }

        return $default;
    }

    public static function getFromZip(string $zipPath): ?Plugin
    {
        $archive = new ZipArchive();
        if (true !== $archive->open($zipPath)) {
            return null;
        }

        $configIndex = $archive->locateName('erpia.ini', ZipArchive::FL_NODIR);
        $iniContent = parse_ini_string($archive->getFromIndex($configIndex));
        
        $plugin = new Plugin();
        $configPath = $archive->getNameIndex($configIndex);
        $plugin->folder = substr($configPath, 0, strpos($configPath, '/'));
        $plugin->loadIniData($iniContent);
        $plugin->enabled = Plugins::isEnabled($plugin->name);
        
        $archive->close();

        return $plugin;
    }

    public function hasUpdate(): bool
    {
        $remoteVersion = $this->forja('version', 0.0);
        return version_compare($this->version, $remoteVersion, '<');
    }

    public function init(): bool
    {
        if ($this->disabled() && !$this->post_disable) {
            return false;
        }

        $initClassName = 'ERPIA\\Plugins\\' . $this->name . '\\Init';
        if (!class_exists($initClassName)) {
            $this->post_disable = false;
            $this->post_enable = false;
            return false;
        }

        $initInstance = new $initClassName();
        
        $updateLock = 'plugin-' . $this->name . '-update-lock';
        if ($this->enabled && $this->post_enable && Kernel::acquireLock($updateLock)) {
            $initInstance->update();
            Kernel::releaseLock($updateLock);
        }
        
        $uninstallLock = 'plugin-' . $this->name . '-uninstall-lock';
        if ($this->disabled() && $this->post_disable && Kernel::acquireLock($uninstallLock)) {
            $initInstance->uninstall();
            Kernel::releaseLock($uninstallLock);
        }
        
        if ($this->enabled) {
            $initInstance->init();
        }

        $completed = $this->post_disable || $this->post_enable;
        
        $this->post_disable = false;
        $this->post_enable = false;

        return $completed;
    }

    private function checkCompatibility(): void
    {
        if (version_compare(PHP_VERSION, $this->min_php, '<')) {
            $this->compatible = false;
            $this->compatibilityMessage = Tools::trans('php-version-incompatible', [
                '%plugin%' => $this->name,
                '%required%' => $this->min_php
            ]);
            return;
        }

        $currentVersion = Kernel::version();
        if (version_compare($currentVersion, $this->min_version, '<')) {
            $this->compatible = false;
            $this->compatibilityMessage = Tools::trans('core-version-incompatible', [
                '%plugin%' => $this->name,
                '%required%' => $this->min_version,
                '%current%' => $currentVersion
            ]);
            return;
        }

        if ($this->min_version < 2025) {
            $this->compatible = false;
            $this->compatibilityMessage = Tools::trans('plugin-outdated', [
                '%plugin%' => $this->name,
                '%version%' => $currentVersion
            ]);
            return;
        }

        $this->compatible = true;
    }

    private function hidden(): bool
    {
        $hiddenPlugins = Tools::config('hidden_plugins', '');
        if ($hiddenPlugins !== '') {
            return in_array($this->name, explode(',', $hiddenPlugins));
        }

        return false;
    }

    private function loadIniData(array $config): void
    {
        $this->description = $config['description'] ?? $this->description;
        $this->min_version = floatval($config['min_version'] ?? 0);
        $this->min_php = floatval($config['min_php'] ?? $this->min_php);
        $this->name = $config['name'] ?? $this->name;

        $this->require = [];
        if ($config['require'] ?? '') {
            foreach (explode(',', $config['require']) as $dependency) {
                $this->require[] = trim($dependency);
            }
        }

        $this->require_php = [];
        if ($config['require_php'] ?? '') {
            foreach (explode(',', $config['require_php']) as $extension) {
                $this->require_php[] = trim($extension);
            }
        }

        $this->version = floatval($config['version'] ?? 0);
        $this->installed = $this->exists();

        $this->hidden = $this->hidden();
        if ($this->disabled()) {
            $this->order = 0;
        }

        $this->checkCompatibility();
    }

    private function loadConfiguration(): void
    {
        $configPath = $this->getFolderPath() . DIRECTORY_SEPARATOR . 'erpia.ini';
        if (!file_exists($configPath)) {
            return;
        }

        $configContent = file_get_contents($configPath);
        $parsedConfig = parse_ini_string($configContent);
        
        if ($parsedConfig) {
            $this->loadIniData($parsedConfig);
        }
    }

    public function folder(): string
    {
        return $this->getFolderPath();
    }
}