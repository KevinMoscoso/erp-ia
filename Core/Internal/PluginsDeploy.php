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

use Exception;
use ERPIA\Core\Tools;
use ERPIA\Core\Translator;
use ERPIA\Dinamic\Model\Page;

final class PluginsDeploy
{
    /** @var array */
    private static $activePlugins = [];
    /** @var array */
    private static $processedFiles = [];
    /** @var array */
    private static $pageRegistry = [];

    public static function initializeControllers(): void
    {
        $controllerFiles = Tools::scanDirectory(Tools::getFolderPath('Dinamic', 'Controller'), false);
        
        foreach ($controllerFiles as $fileItem) {
            if (substr($fileItem, -4) !== '.php') {
                continue;
            }
            
            $controllerBaseName = basename($fileItem, '.php');
            if ($controllerBaseName === 'Installer' || strpos($controllerBaseName, 'Api') === 0) {
                continue;
            }
            
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $controllerBaseName)) {
                Tools::log()->warning('Invalid controller identifier: ' . $controllerBaseName);
                continue;
            }
            
            $controllerClass = '\\ERPIA\\Dinamic\\Controller\\' . $controllerBaseName;
            Tools::log()->debug('Initializing controller: ' . $controllerBaseName);
            
            if (!class_exists($controllerClass)) {
                require Tools::getFolderPath('Dinamic', 'Controller', $controllerBaseName . '.php');
            }
            
            try {
                $controllerInstance = new $controllerClass($controllerBaseName);
                self::registerPage($controllerInstance->getPageData());
            } catch (Exception $ex) {
                Tools::log()->critical('controller-load-failure', ['%controller%' => $controllerBaseName]);
                Tools::log()->critical($ex->getMessage());
            }
        }
        
        self::cleanupOrphanPages();
        
        $needsConfigUpdate = false;
        $homepageSetting = Tools::settings('default', 'homepage', '');
        
        if (!in_array($homepageSetting, self::$pageRegistry)) {
            Tools::settingsSet('default', 'homepage', 'AdminPlugins');
            $needsConfigUpdate = true;
        }
        
        if ($needsConfigUpdate) {
            Tools::settingsSave();
        }
    }

    public static function executeDeployment(array $enabledPlugins, bool $cleanOutput = true): void
    {
        self::$activePlugins = array_reverse($enabledPlugins);
        self::$processedFiles = [];
        
        $deploymentFolders = ['Assets', 'Controller', 'Data', 'Error', 'Lib', 'Model', 'Table', 'View', 'Worker', 'XMLView'];
        
        foreach ($deploymentFolders as $folderType) {
            if ($cleanOutput) {
                Tools::deleteDirectory(Tools::getFolderPath('Dinamic', $folderType));
            }
            
            Tools::ensureDirectory(Tools::getFolderPath('Dinamic', $folderType));
            
            foreach (self::$activePlugins as $pluginIdentifier) {
                $pluginFolder = Tools::getFolderPath('Plugins', $pluginIdentifier, $folderType);
                if (file_exists($pluginFolder)) {
                    self::processFolder($folderType, 'Plugins', $pluginIdentifier);
                }
            }
            
            $coreFolder = Tools::getFolderPath('Core', $folderType);
            if (file_exists($coreFolder)) {
                self::processFolder($folderType);
            }
        }
        
        Translator::deploy();
        Translator::reload();
    }

    private static function hasExtensionSupport(string $namespace): bool
    {
        return $namespace === 'ERPIA\Dinamic\Controller';
    }

    private static function determineClassType(string $fileName, string $folder, string $source, string $pluginName): string
    {
        $fileLocation = empty($pluginName) ?
            Tools::getFolderPath($source, $folder, $fileName) :
            Tools::getFolderPath($source, $pluginName, $folder, $fileName);
            
        if (!file_exists($fileLocation)) {
            throw new Exception('Cannot locate class file: ' . $fileName . ' at ' . $fileLocation);
        }
        
        if (!is_file($fileLocation)) {
            throw new Exception('Path is not a file: ' . $fileLocation);
        }
        
        if (!is_readable($fileLocation)) {
            throw new Exception('File is not readable: ' . $fileLocation);
        }
        
        $fileContent = file_get_contents($fileLocation);
        $tokens = token_get_all($fileContent);
        
        $abstractDetected = false;
        $classDetected = false;
        
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }
            
            if ($token[0] === T_ABSTRACT) {
                $abstractDetected = true;
            }
            
            if ($token[0] === T_CLASS) {
                $classDetected = true;
                break;
            }
            
            if (in_array($token[0], [T_FUNCTION, T_INTERFACE, T_TRAIT])) {
                $abstractDetected = false;
            }
        }
        
        if (!$classDetected) {
            return '';
        }
        
        return $abstractDetected ? 'abstract class' : 'class';
    }

    private static function copyResourceFile(string $fileName, string $folder, string $sourcePath): void
    {
        $targetPath = Tools::getFolderPath('Dinamic', $folder, $fileName);
        
        if (!copy($sourcePath, $targetPath)) {
            throw new Exception('File copy failed: ' . $sourcePath . ' to ' . $targetPath);
        }
        
        self::$processedFiles[$folder][$fileName] = $fileName;
    }

    private static function processFolder(string $folder, string $source = 'Core', string $pluginName = ''): void
    {
        $sourcePath = empty($pluginName) ?
            Tools::getFolderPath($source, $folder) :
            Tools::getFolderPath($source, $pluginName, $folder);
            
        $folderContents = Tools::scanDirectory($sourcePath, true);
        
        foreach ($folderContents as $itemName) {
            if (isset(self::$processedFiles[$folder][$itemName])) {
                continue;
            }
            
            $itemPath = Tools::getFolderPath($sourcePath, $itemName);
            $pathInfo = pathinfo($itemName);
            
            if (is_dir($itemPath)) {
                Tools::ensureDirectory(Tools::getFolderPath('Dinamic', $folder, $itemName));
                continue;
            } elseif ($pathInfo['filename'] === '' || !is_file($itemPath)) {
                continue;
            }
            
            $fileExtension = $pathInfo['extension'] ?? '';
            
            switch ($fileExtension) {
                case 'php':
                    self::handlePhpFile($itemName, $folder, $source, $pluginName);
                    break;
                case 'xml':
                    self::handleXmlFile($itemName, $folder, $itemPath);
                    break;
                default:
                    self::copyResourceFile($itemName, $folder, $itemPath);
            }
        }
    }

    private static function handlePhpFile(string $fileName, string $folder, string $source, string $pluginName): void
    {
        $namespaceBase = empty($pluginName) ? $source : 'Plugins\\' . $pluginName;
        $originalNamespace = 'ERPIA\\' . $namespaceBase . '\\' . $folder;
        $dynamicNamespace = "ERPIA\\Dinamic\\" . $folder;
        
        $pathSegments = explode(DIRECTORY_SEPARATOR, $fileName);
        $segmentCount = count($pathSegments);
        
        for ($index = 0; $index < $segmentCount - 1; ++$index) {
            $originalNamespace .= '\\' . $pathSegments[$index];
            $dynamicNamespace .= '\\' . $pathSegments[$index];
        }
        
        $className = basename($fileName, '.php');
        $classType = self::determineClassType($fileName, $folder, $source, $pluginName);
        
        if (empty($classType)) {
            return;
        }
        
        $classDefinition = '<?php namespace ' . $dynamicNamespace . ";\n\n"
            . '/**' . "\n"
            . ' * Auto-generated by ERPIA PluginsDeploy' . "\n"
            . ' * @author ERPIA Development Team' . "\n"
            . ' */' . "\n"
            . $classType . ' ' . $className . ' extends \\' . $originalNamespace . '\\' . $className;
            
        $classDefinition .= self::hasExtensionSupport($dynamicNamespace) ? 
            "\n{\n\tuse \\ERPIA\\Core\\Template\\ExtensionTrait;\n}\n" : "\n{\n}\n";
            
        $outputPath = Tools::getFolderPath('Dinamic', $folder, $fileName);
        
        if (file_put_contents($outputPath, $classDefinition) === false) {
            throw new Exception('Cannot write PHP file: ' . $outputPath);
        }
        
        self::$processedFiles[$folder][$fileName] = $fileName;
    }

    private static function handleXmlFile(string $fileName, string $folder, string $sourcePath): void
    {
        $extensionFiles = [];
        
        foreach (self::$activePlugins as $pluginId) {
            $extensionPath = Tools::getFolderPath('Plugins', $pluginId, 'Extension', $folder, $fileName);
            if (file_exists($extensionPath)) {
                $extensionFiles[] = $extensionPath;
            }
        }
        
        $baseXml = simplexml_load_file($sourcePath);
        if ($baseXml === false) {
            $xmlErrors = libxml_get_errors();
            $errorMessage = !empty($xmlErrors) ? $xmlErrors[0]->message : 'XML parsing error';
            throw new Exception('Cannot load XML: ' . $sourcePath . ' - ' . $errorMessage);
        }
        
        foreach ($extensionFiles as $extensionFile) {
            $extensionXml = simplexml_load_file($extensionFile);
            if ($extensionXml === false) {
                $xmlErrors = libxml_get_errors();
                $errorMessage = !empty($xmlErrors) ? $xmlErrors[0]->message : 'XML parsing error';
                throw new Exception('Cannot load XML extension: ' . $extensionFile . ' - ' . $errorMessage);
            }
            self::mergeXmlDocuments($baseXml, $extensionXml);
        }
        
        $outputPath = Tools::getFolderPath('Dinamic', $folder, $fileName);
        if ($baseXml->asXML($outputPath) === false) {
            throw new Exception('Cannot write XML file: ' . $outputPath);
        }
        
        self::$processedFiles[$folder][$fileName] = $fileName;
    }

    private static function registerPage(array $pageData): void
    {
        if (empty($pageData)) {
            return;
        }
        
        self::$pageRegistry[] = $pageData['name'];
        
        $pageModel = new Page();
        if ($pageModel->load($pageData['name']) === false) {
            $pageData['ordernum'] = 100;
            $pageModel->loadFromArray($pageData);
            $pageModel->save();
            return;
        }
        
        $requiresUpdate = $pageModel->menu !== $pageData['menu'] ||
            $pageModel->submenu !== $pageData['submenu'] ||
            $pageModel->title !== $pageData['title'] ||
            $pageModel->icon !== $pageData['icon'] ||
            $pageModel->showonmenu !== $pageData['showonmenu'];
            
        if ($requiresUpdate) {
            $pageModel->loadFromArray($pageData);
            $pageModel->save();
        }
    }

    private static function mergeXmlDocuments(&$baseDocument, $extensionDocument): void
    {
        foreach ($extensionDocument->children() as $extensionNode) {
            $position = -1;
            $nodeFound = false;
            
            foreach ($baseDocument->children() as $baseNode) {
                if ($baseNode->getName() == $extensionNode->getName()) {
                    $position++;
                }
                
                if (!self::compareXmlNodes($baseNode, $extensionNode)) {
                    continue;
                }
                
                $nodeFound = true;
                $extensionDom = dom_import_simplexml($extensionNode);
                if ($extensionDom === false) {
                    throw new Exception('Failed to convert extension node to DOM');
                }
                
                $overwriteFlag = mb_strtolower($extensionDom->getAttribute('overwrite'));
                
                switch ($overwriteFlag) {
                    case 'true':
                        $baseDom = dom_import_simplexml($baseDocument);
                        if ($baseDom === false) {
                            throw new Exception('Failed to convert base document to DOM');
                        }
                        
                        $importedNode = $baseDom->ownerDocument->importNode($extensionDom, true);
                        $targetNodes = $baseDom->getElementsByTagName($importedNode->nodeName);
                        $targetNode = $targetNodes->item($position);
                        
                        if ($targetNode === null) {
                            throw new Exception('Target node not found at position ' . $position);
                        }
                        
                        $baseDom->replaceChild($importedNode, $targetNode);
                        break;
                        
                    default:
                        self::mergeXmlDocuments($baseNode, $extensionNode);
                }
                break;
            }
            
            if (!$nodeFound) {
                $baseDom = dom_import_simplexml($baseDocument);
                if ($baseDom === false) {
                    throw new Exception('Failed to convert base document to DOM');
                }
                
                $extensionDom = dom_import_simplexml($extensionNode);
                if ($extensionDom === false) {
                    throw new Exception('Failed to convert extension node to DOM');
                }
                
                $importedNode = $baseDom->ownerDocument->importNode($extensionDom, true);
                $overwriteFlag = mb_strtolower($extensionDom->getAttribute('overwrite'));
                
                switch ($overwriteFlag) {
                    case 'true':
                        $targetNodes = $baseDom->getElementsByTagName('*');
                        $targetNode = $targetNodes->item($position);
                        
                        if ($targetNode === null) {
                            throw new Exception('No target node at position ' . $position);
                        }
                        
                        $baseDom->replaceChild($importedNode, $targetNode);
                        break;
                        
                    default:
                        $baseDom->appendChild($importedNode);
                }
            }
        }
    }

    private static function compareXmlNodes($baseNode, $extensionNode): bool
    {
        if ($baseNode->getName() != $extensionNode->getName()) {
            return false;
        }
        
        foreach ($extensionNode->attributes() as $extAttr => $extValue) {
            if ($extAttr != 'name' && $extensionNode->getName() != 'row') {
                continue;
            } elseif ($extAttr != 'type' && $extensionNode->getName() == 'row') {
                continue;
            }
            
            foreach ($baseNode->attributes() as $baseAttr => $baseValue) {
                if ($baseAttr == $extAttr) {
                    return (string)$extValue == (string)$baseValue;
                }
            }
        }
        
        return in_array($extensionNode->getName(), ['columns', 'modals', 'rows']);
    }

    private static function cleanupOrphanPages(): void
    {
        $allPages = Page::all();
        
        foreach ($allPages as $pageRecord) {
            if (!in_array($pageRecord->name, self::$pageRegistry, true)) {
                $pageRecord->delete();
            }
        }
    }

    public static function initControllers(): void
    {
        self::initializeControllers();
    }

    public static function run(array $enabledPlugins, bool $clean = true): void
    {
        self::executeDeployment($enabledPlugins, $clean);
    }
}