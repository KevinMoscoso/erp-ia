<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la entrega segura de archivos estáticos
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Contract\ControllerInterface;
use ERPIA\Core\KernelException;
use ERPIA\Core\Helpers;
use ERPIA\Core\FileSystem;

/**
 * Controlador para servir archivos estáticos de manera segura
 */
class Files implements ControllerInterface
{
    /**
     * @var string Ruta completa del archivo a servir
     */
    private $filePath = '';

    /**
     * Constructor del controlador de archivos
     *
     * @param string $className Nombre de la clase (no utilizado)
     * @param string $url Ruta del archivo solicitado
     */
    public function __construct(string $className, string $url = '')
    {
        if (empty($url)) {
            return;
        }

        // Manejo especial para el favicon
        if ('/favicon.ico' == $url) {
            $this->filePath = FileSystem::getPath(['Core', 'Assets', 'Images', 'favicon.ico']);
            return;
        }

        $this->filePath = FileSystem::getBasePath() . $url;

        if (is_file($this->filePath) === false) {
            throw new KernelException(
                'ArchivoNoEncontrado',
                Helpers::translate('archivo-no-encontrado', ['%nombreArchivo%' => $url])
            );
        }

        if ($this->isFolderSafe($url) === false) {
            throw new KernelException('CarpetaNoSegura', $url);
        }

        if ($this->isFileSafe($this->filePath) === false) {
            throw new KernelException('ArchivoNoSeguro', $url);
        }
    }

    /**
     * Obtiene los datos de configuración de la página
     *
     * @return array
     */
    public function getPageData(): array
    {
        return [];
    }

    /**
     * Verifica si un archivo es seguro basándose en su extensión
     *
     * @param string $filePath Ruta completa del archivo
     * @return bool
     */
    public static function isFileSafe(string $filePath): bool
    {
        $pathSegments = explode('.', $filePath);
        $extensionList = [
            'accdb', 'avi', 'cdr', 'css', 'csv', 'doc', 'docx', 'eot', 'gif', 'gz', 'html', 'ico', 'ics', 'jpeg',
            'jpg', 'js', 'json', 'map', 'md', 'mdb', 'mkv', 'mp3', 'mp4', 'ndg', 'ods', 'odt', 'ogg', 'pdf', 'png',
            'pptx', 'sql', 'svg', 'ttf', 'txt', 'webm', 'woff', 'woff2', 'xls', 'xlsx', 'xml', 'xsig', 'zip'
        ];
        return empty($pathSegments) || count($pathSegments) === 1 || 
               in_array(end($pathSegments), $extensionList, true);
    }

    /**
     * Verifica si una carpeta es segura para acceder a archivos
     *
     * @param string $filePath Ruta del archivo
     * @return bool
     */
    public static function isFolderSafe(string $filePath): bool
    {
        $allowedFolders = ['node_modules', 'vendor', 'Dinamic', 'Core', 'Plugins', 'MyFiles/Public'];
        foreach ($allowedFolders as $folder) {
            if ('/' . $folder === substr($filePath, 0, 1 + strlen($folder))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ejecuta el controlador para servir el archivo
     */
    public function run(): void
    {
        if (empty($this->filePath)) {
            return;
        }

        header('Content-Type: ' . $this->getMimeType($this->filePath));

        // Deshabilitar buffer si está activo
        if (ob_get_length() > 0) {
            ob_end_flush();
        }

        // Forzar descarga de archivos SVG para prevenir ataques XSS
        if (strpos($this->filePath, '.svg') !== false) {
            header('Content-Disposition: attachment; filename="' . basename($this->filePath) . '"');
        }

        readfile($this->filePath);
    }

    /**
     * Determina el tipo MIME de un archivo
     *
     * @param string $filePath Ruta del archivo
     * @return string Tipo MIME
     */
    private function getMimeType(string $filePath): string
    {
        $fileInfo = pathinfo($filePath);
        $fileExtension = strtolower($fileInfo['extension'] ?? '');
        
        switch ($fileExtension) {
            case 'css':
                return 'text/css';

            case 'js':
                return 'application/javascript';

            case 'md':
                return 'text/markdown';

            case 'xml':
            case 'xsig':
                return 'text/xml';
        }

        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }

        // Fallback para sistemas sin mime_content_type
        return $this->getMimeByExtension($fileExtension);
    }

    /**
     * Obtiene el tipo MIME basándose en la extensión del archivo
     *
     * @param string $extension Extensión del archivo
     * @return string Tipo MIME
     */
    private function getMimeByExtension(string $extension): string
    {
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'ico' => 'image/x-icon',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}