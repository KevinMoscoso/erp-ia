<?php
/**
 * ERPIA - Sistema de Gestión Empresarial
 * Este archivo es parte de ERPIA, software libre bajo licencia GPL.
 * 
 * @package    ERPIA\Core\Controller
 * @author     Equipo de Desarrollo ERPIA
 * @copyright  2023-2025 ERPIA
 * @license    GNU Lesser General Public License v3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\Controller;
use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Cache;
use ERPIA\Core\Http;
use ERPIA\Core\Internal\Forja;
use ERPIA\Core\Internal\Plugin;
use ERPIA\Core\Kernel;
use ERPIA\Core\Migrations;
use ERPIA\Core\Plugins;
use ERPIA\Core\Response;
use ERPIA\Core\Telemetry;
use ERPIA\Core\Translator;
use ERPIA\Core\Config;
use ERPIA\Core\Utility\FileSystem;
use ERPIA\Dinamic\Model\User;
use ZipArchive;

/**
 * Controlador para gestionar actualizaciones del sistema ERPIA
 * 
 * Permite comprobar, descargar e instalar actualizaciones
 * del núcleo y plugins desde el servidor de forja.
 */
class Updater extends Controller
{
    const CORE_ZIP_FOLDER = 'erpia';
    const UPDATE_CORE_URL = 'https://erpia.org/DownloadBuild';

    /** @var array */
    public $coreUpdateWarnings = [];

    /** @var Telemetry */
    public $telemetryManager;

    /** @var array */
    public $updaterItems = [];

    /**
     * Constructor del controlador
     * 
     * @param string $className Nombre de la clase
     * @param string $uri URI del controlador
     */
    public function __construct(string $className, string $uri = '')
    {
        $config = Config::getInstance();
        $basePath = $config->get('base_path', '');

        // Si no existe el archivo Empresa en Dinamic, reconstruimos
        if (!file_exists($basePath . '/Dinamic/Model/Empresa.php')) {
            Plugins::deploy(true, false);
        }

        parent::__construct($className, $uri);
    }

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título e icono
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'updater';
        $pageData['icon'] = 'fa-solid fa-cloud-download-alt';
        
        return $pageData;
    }

    /**
     * Obtiene la versión actual del núcleo
     * 
     * @return float Versión del núcleo
     */
    public static function getCoreVersion(): float
    {
        return Kernel::version();
    }

    /**
     * Obtiene la lista de elementos actualizables
     * 
     * @return array Elementos para actualizar
     */
    public static function getUpdateItems(): array
    {
        $items = [];

        // Comprobamos si se puede actualizar el núcleo
        if (Forja::canUpdateCore()) {
            $item = self::getUpdateItemsCore();
            if (!empty($item)) {
                $items[] = $item;
            }
        }

        // Comprobamos si se puede actualizar algún plugin
        foreach (Plugins::list() as $plugin) {
            $item = self::getUpdateItemsPlugin($plugin);
            if (!empty($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Ejecuta la lógica privada del controlador
     * 
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);
        
        $this->telemetryManager = new Telemetry();
        
        // Verificar permisos de escritura en carpetas
        $folders = $this->notWritableFolders();
        if ($folders) {
            Translator::log()->warning('folders-not-writable', [
                '%folders%' => implode(', ', $folders)
            ]);
            return;
        }

        $action = $this->request->get('action', '');
        $this->execAction($action);
    }

    /**
     * Elimina un archivo descargado
     */
    private function cancelAction(): void
    {
        $fileName = 'update-' . $this->request->get('item', '') . '.zip';
        $config = Config::getInstance();
        $basePath = $config->get('base_path', '');
        $filePath = $basePath . '/' . $fileName;

        if (file_exists($filePath)) {
            unlink($filePath);
            Translator::log()->notice('record-deleted-correctly');
        }

        Translator::log()->notice('reloading');
        $this->redirect($this->getClassName() . '?action=post-update', 3);
    }

    /**
     * Desactiva las actualizaciones beta
     */
    private function disableBetaUpdatesAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $config = Config::getInstance();
        $config->set('default.enableupdatesbeta', false);
        $config->save();

        Translator::log()->notice('record-updated-correctly');
    }

    /**
     * Descarga la actualización seleccionada
     */
    private function downloadAction(): void
    {
        $idItem = $this->request->get('item', '');
        $this->updaterItems = self::getUpdateItems();
        $config = Config::getInstance();
        $basePath = $config->get('base_path', '');

        foreach ($this->updaterItems as $key => $item) {
            if ($item['id'] != $idItem) {
                continue;
            }

            $filePath = $basePath . '/' . $item['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $url = $this->telemetryManager->signUrl($item['url']);
            $http = Http::get($url);

            if ($http->saveAs($filePath)) {
                Translator::log()->notice('download-completed');
                $this->updaterItems[$key]['downloaded'] = true;
                break;
            }

            Translator::log()->error('download-error', [
                '%body%' => $http->body(),
                '%error%' => $http->errorMessage(),
                '%status%' => $http->status(),
            ]);
        }

        // Desactivar plugins si es necesario
        $disable = $this->request->get('disable', '');
        foreach (explode(',', $disable) as $plugin) {
            Plugins::disable($plugin);
        }
    }

    /**
     * Ejecuta la acción solicitada
     * 
     * @param string $action Acción a ejecutar
     */
    protected function execAction(string $action): void
    {
        switch ($action) {
            case 'cancel':
                $this->cancelAction();
                return;
            case 'claim-install':
                $this->redirect($this->telemetryManager->claimUrl());
                return;
            case 'disable-beta':
                $this->disableBetaUpdatesAction();
                return;
            case 'download':
                $this->downloadAction();
                return;
            case 'post-update':
                $this->postUpdateAction();
                break;
            case 'register':
                if ($this->telemetryManager->install()) {
                    Translator::log()->notice('record-updated-correctly');
                    break;
                }
                Translator::log()->error('record-save-error');
                break;
            case 'unlink':
                if ($this->telemetryManager->unlink()) {
                    $this->telemetryManager = new Telemetry();
                    Translator::log()->notice('unlink-install-ok');
                    break;
                }
                Translator::log()->error('unlink-install-ko');
                break;
            case 'update':
                $this->updateAction();
                return;
        }

        $this->updaterItems = self::getUpdateItems();
        $this->setCoreWarnings();
    }

    /**
     * Obtiene los elementos de actualización del núcleo
     * 
     * @return array Elemento de actualización del núcleo
     */
    private static function getUpdateItemsCore(): array
    {
        $config = Config::getInstance();
        $basePath = $config->get('base_path', '');
        $fileName = 'update-' . Forja::CORE_PROJECT_ID . '.zip';
        $filePath = $basePath . '/' . $fileName;

        foreach (Forja::getBuilds(Forja::CORE_PROJECT_ID) as $build) {
            if ($build['version'] <= self::getCoreVersion()) {
                continue;
            }

            $item = [
                'description' => Translator::getInstance()->trans('core-update', ['%version%' => $build['version']]),
                'downloaded' => file_exists($filePath),
                'filename' => $fileName,
                'id' => Forja::CORE_PROJECT_ID,
                'name' => 'CORE',
                'stable' => $build['stable'],
                'url' => self::UPDATE_CORE_URL . '/' . Forja::CORE_PROJECT_ID . '/' . $build['version'],
                'version' => $build['version'],
                'mincore' => 0,
                'maxcore' => 0
            ];

            if ($build['stable']) {
                return $item;
            }

            if ($build['beta'] && $config->get('default.enableupdatesbeta', false)) {
                return $item;
            }
        }

        return [];
    }

    /**
     * Obtiene los elementos de actualización de un plugin
     * 
     * @param Plugin $plugin Plugin a verificar
     * @return array Elemento de actualización del plugin
     */
    private static function getUpdateItemsPlugin(Plugin $plugin): array
    {
        $config = Config::getInstance();
        $basePath = $config->get('base_path', '');
        $id = $plugin->forja('idplugin', 0);
        $fileName = 'update-' . $id . '.zip';
        $filePath = $basePath . '/' . $fileName;

        foreach (Forja::getBuilds($id) as $build) {
            if ($build['version'] <= $plugin->version) {
                continue;
            }

            $item = [
                'description' => Translator::getInstance()->trans('plugin-update', [
                    '%pluginName%' => $plugin->name,
                    '%version%' => $build['version']
                ]),
                'downloaded' => file_exists($filePath),
                'filename' => $fileName,
                'id' => $id,
                'name' => $plugin->name,
                'stable' => $build['stable'],
                'url' => self::UPDATE_CORE_URL . '/' . $id . '/' . $build['version'],
                'version' => $build['version'],
                'mincore' => $build['mincore'],
                'maxcore' => $build['maxcore']
            ];

            if ($build['stable']) {
                return $item;
            }

            if ($build['beta'] && $config->get('default.enableupdatesbeta', false)) {
                return $item;
            }
        }

        return [];
    }

    /**
     * Obtiene la lista de carpetas no escribibles
     * 
     * @return array Carpetas no escribibles
     */
    private function notWritableFolders(): array
    {
        $notWritable = [];
        $config = Config::getInstance();
        $basePath = $config->get('base_path', '');
        $foldersToCheck = ['Core', 'Dinamic', 'MyFiles', 'Plugins', 'vendor', 'node_modules'];

        foreach ($foldersToCheck as $folderName) {
            $folderPath = $basePath . '/' . $folderName;

            if (!is_dir($folderPath)) {
                continue;
            }

            if (!is_writable($folderPath)) {
                $notWritable[] = $folderName;
                continue;
            }

            foreach (FileSystem::scanDirectory($folderPath, true) as $subFolder) {
                $subFolderPath = $folderPath . '/' . $subFolder;
                if (is_dir($subFolderPath) && !is_writable($subFolderPath)) {
                    $notWritable[] = $folderName . '/' . $subFolder;
                }
            }
        }

        return $notWritable;
    }

    /**
     * Acciones posteriores a la actualización
     */
    private function postUpdateAction(): void
    {
        $plugName = $this->request->get('init', '');
        if ($plugName) {
            Plugins::deploy(true, true);
            return;
        }

        Migrations::run();
        Plugins::deploy(true, true);
    }

    /**
     * Configura las advertencias de compatibilidad con el núcleo
     */
    private function setCoreWarnings(): void
    {
        // Buscar actualización del núcleo
        $newCore = 0;
        foreach ($this->updaterItems as $item) {
            if ($item['id'] === Forja::CORE_PROJECT_ID) {
                $newCore = $item['version'];
                break;
            }
        }

        if (empty($newCore)) {
            return;
        }

        // Comprobar plugins instalados
        foreach (Plugins::list() as $plugin) {
            if (false === $plugin->enabled) {
                continue;
            }

            if ($this->willItWorkOnNewCore($plugin, $newCore)) {
                continue;
            }

            if ($plugin->forja('maxcore', 0) >= $newCore) {
                $this->coreUpdateWarnings[$plugin->name] = Translator::getInstance()->trans('plugin-need-update', [
                    '%plugin%' => $plugin->name
                ]);
                continue;
            }

            $this->coreUpdateWarnings[$plugin->name] = Translator::getInstance()->trans('plugin-need-update-but', [
                '%plugin%' => $plugin->name
            ]);
        }
    }

    /**
     * Verifica si un plugin funcionará con la nueva versión del núcleo
     * 
     * @param Plugin $plugin Plugin a verificar
     * @param float $newCore Nueva versión del núcleo
     * @return bool True si funcionará
     */
    private function willItWorkOnNewCore(Plugin $plugin, float $newCore): bool
    {
        foreach (Forja::getBuildsByName($plugin->name) as $build) {
            if ($build['version'] == $plugin->version) {
                return $build['maxcore'] >= $newCore;
            }
        }

        return false;
    }

    /**
     * Extrae el archivo ZIP y aplica la actualización
     */
    private function updateAction(): void
    {
        $idItem = $this->request->get('item', '');
        $fileName = 'update-' . $idItem . '.zip';
        $config = Config::getInstance();
        $basePath = $config->get('base_path', '');
        $filePath = $basePath . '/' . $fileName;

        // Abrir el archivo ZIP
        $zip = new ZipArchive();
        $zipStatus = $zip->open($filePath, ZipArchive::CHECKCONS);

        if ($zipStatus !== true) {
            Translator::log()->critical('ZIP ERROR: ' . $zipStatus);
            return;
        }

        // Obtener el nombre del plugin para inicializar después de la actualización
        $init = '';
        foreach (self::getUpdateItems() as $item) {
            if ($idItem == Forja::CORE_PROJECT_ID) {
                break;
            }

            if ($item['id'] == $idItem && Plugins::isEnabled($item['name'])) {
                $init = $item['name'];
                break;
            }
        }

        // Extraer núcleo o plugin
        $done = ($idItem == Forja::CORE_PROJECT_ID) ?
            $this->updateCore($zip, $filePath) :
            $this->updatePlugin($zip, $filePath);

        if ($done) {
            Plugins::deploy(true, false);
            Cache::clear();
            $this->setTemplate(false);
            $this->redirect($this->getClassName() . '?action=post-update&init=' . $init, 3);
        }
    }

    /**
     * Actualiza el núcleo desde un archivo ZIP
     * 
     * @param ZipArchive $zip Archivo ZIP abierto
     * @param string $filePath Ruta del archivo ZIP
     * @return bool True si la actualización fue exitosa
     */
    private function updateCore(ZipArchive $zip, string $filePath): bool
    {
        $config = Config::getInstance();
        $basePath = $config->get('base_path', '');

        // Extraer contenido del ZIP
        if (false === $zip->extractTo($basePath)) {
            Translator::log()->critical('ZIP EXTRACT ERROR: ' . $filePath);
            $zip->close();
            return false;
        }

        // Cerrar y eliminar archivo ZIP
        $zip->close();
        unlink($filePath);

        // Actualizar carpetas
        foreach (['Core', 'node_modules', 'vendor'] as $folder) {
            $origin = $basePath . '/' . self::CORE_ZIP_FOLDER . '/' . $folder;
            $dest = $basePath . '/' . $folder;

            if (false === file_exists($origin)) {
                Translator::log()->critical('COPY ERROR: ' . $origin);
                return false;
            }

            FileSystem::deleteDirectory($dest);
            if (false === FileSystem::copyDirectory($origin, $dest)) {
                Translator::log()->critical('COPY ERROR2: ' . $origin);
                return false;
            }
        }

        // Actualizar archivos
        foreach (['index.php', 'replace_index_to_restore.php'] as $name) {
            $origin = $basePath . '/' . self::CORE_ZIP_FOLDER . '/' . $name;
            $dest = $basePath . '/' . $name;
            copy($origin, $dest);
        }

        // Eliminar carpeta temporal
        FileSystem::deleteDirectory($basePath . '/' . self::CORE_ZIP_FOLDER);
        return true;
    }

    /**
     * Actualiza un plugin desde un archivo ZIP
     * 
     * @param ZipArchive $zip Archivo ZIP abierto
     * @param string $filePath Ruta del archivo ZIP
     * @return bool True si la actualización fue exitosa
     */
    private function updatePlugin(ZipArchive $zip, string $filePath): bool
    {
        $zip->close();
        $return = Plugins::add($filePath, 'plugin.zip', true);
        unlink($filePath);
        return $return;
    }

    /**
     * Valida el token del formulario para prevenir CSRF
     * 
     * @return bool True si el token es válido
     */
    protected function validateFormToken(): bool
    {
        $token = $this->request->inputOrQuery('multireqtoken', '');

        if (empty($token)) {
            Translator::log()->warning('invalid-request');
            return false;
        }

        $sessionToken = $this->request->session()->get('form_token');

        if ($sessionToken !== $token) {
            Translator::log()->warning('invalid-request');
            return false;
        }

        return true;
    }
}