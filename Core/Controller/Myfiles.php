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

use ERPIA\Core\Contract\ControllerInterface;
use ERPIA\Core\KernelException;
use ERPIA\Core\Lib\MyFilesToken;
use ERPIA\Core\Translator;

/**
 * Controlador para gestionar la descarga de archivos desde el directorio MyFiles
 * 
 * Valida la seguridad de los archivos, verifica tokens de acceso y envía
 * los archivos con los headers HTTP apropiados.
 */
class Myfiles implements ControllerInterface
{
    /** @var string Ruta completa del archivo a servir */
    private $filePath = '';

    /**
     * Constructor del controlador
     * 
     * @param string $className Nombre de la clase (no utilizado)
     * @param string $url Ruta del archivo solicitado
     */
    public function __construct(string $className, string $url = '')
    {
        if (empty($url)) {
            return;
        }

        // Verificar que la URL comience con /MyFiles/
        if (strpos($url, '/MyFiles/') !== 0) {
            return;
        }

        // Construir ruta completa del archivo
        $config = \ERPIA\Core\Config::getInstance();
        $basePath = $config->get('base_path', '');
        $this->filePath = $basePath . urldecode($url);

        // Validar que el archivo exista
        if (false === is_file($this->filePath)) {
            throw new KernelException(
                'FileNotFound',
                Translator::getInstance()->trans('file-not-found', ['%fileName%' => $url])
            );
        }

        // Validar que el archivo sea seguro
        if (false === self::isFileSafe($this->filePath)) {
            throw new KernelException('UnsafeFile', $url);
        }

        // Si el archivo está en la carpeta pública, no requiere token
        if (strpos($url, '/MyFiles/Public/') === 0) {
            return;
        }

        // Validar token para archivos no públicos
        $fixedFilePath = substr(urldecode($url), 1); // Eliminar la barra inicial
        $token = filter_input(INPUT_GET, 'myft');
        if (empty($token) || false === MyFilesToken::validate($fixedFilePath, $token)) {
            throw new KernelException('MyfilesTokenError', $fixedFilePath);
        }
    }

    /**
     * Obtiene los metadatos de la página (no aplica para este controlador)
     * 
     * @return array Array vacío
     */
    public function getPageData(): array
    {
        return [];
    }

    /**
     * Verifica si un archivo tiene una extensión segura
     * 
     * @param string $filePath Ruta del archivo
     * @return bool True si la extensión está en la lista blanca
     */
    public static function isFileSafe(string $filePath): bool
    {
        $parts = explode('.', $filePath);
        $safeExtensions = [
            '7z', 'accdb', 'ai', 'avi', 'cdr', 'css', 'csv', 'doc', 'docx', 'dxf', 'dwg', 'eot', 'gif', 'gz', 'html',
            'ico', 'ics', 'jfif', 'jpeg', 'jpg', 'js', 'json', 'map', 'md', 'mdb', 'mkv', 'mov', 'mp3', 'mp4', 'ndg',
            'ods', 'odt', 'ogg', 'pdf', 'png', 'pptx', 'rar', 'sql', 'step', 'svg', 'ttf', 'txt', 'webm', 'webp',
            'woff', 'woff2', 'xls', 'xlsm', 'xlsx', 'xml', 'xsig', 'zip'
        ];
        
        $extension = strtolower(end($parts));
        return empty($parts) || count($parts) === 1 || in_array($extension, $safeExtensions, true);
    }

    /**
     * Envía el archivo al cliente
     */
    public function run(): void
    {
        if (empty($this->filePath)) {
            return;
        }

        // Establecer el tipo MIME
        header('Content-Type: ' . $this->getMime($this->filePath));

        // Limpiar buffer de salida si está activo
        if (ob_get_contents()) {
            ob_end_flush();
        }

        // Forzar descarga de archivos SVG por seguridad
        if ($this->isSvg($this->filePath)) {
            header('Content-Disposition: attachment; filename="' . basename($this->filePath) . '"');
        }

        // Enviar el archivo
        readfile($this->filePath);
    }

    /**
     * Determina el tipo MIME de un archivo
     * 
     * @param string $filePath Ruta del archivo
     * @return string Tipo MIME
     */
    private function getMime(string $filePath): string
    {
        $info = pathinfo($filePath);
        $extension = strtolower($info['extension'] ?? '');
        
        switch ($extension) {
            case 'css':
                return 'text/css';
            case 'js':
                return 'application/javascript';
            case 'xml':
            case 'xsig':
                return 'text/xml';
        }

        // Usar mime_content_type para otros tipos
        return mime_content_type($filePath) ?: 'application/octet-stream';
    }

    /**
     * Verifica si un archivo es SVG
     * 
     * @param string $filePath Ruta del archivo
     * @return bool True si es SVG
     */
    private function isSvg(string $filePath): bool
    {
        // Verificar por extensión
        if (strpos($filePath, '.svg') !== false) {
            return true;
        }

        // Verificar por tipo MIME
        if (strpos($this->getMime($filePath), 'image/svg') !== false) {
            return true;
        }

        return false;
    }
}