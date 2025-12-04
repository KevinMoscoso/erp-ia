<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la instalación del sistema
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use DateTimeZone;
use Exception;
use ERPIA\Core\Contract\ControllerInterface;
use ERPIA\Core\Html;
use ERPIA\Core\Kernel;
use ERPIA\Core\KernelException;
use ERPIA\Core\Plugins;
use ERPIA\Core\Request;
use ERPIA\Core\Helpers;
use ERPIA\Core\Config;
use ERPIA\Core\FileSystem;

/**
 * Controlador para la instalación del sistema ERPIA
 */
class Installer implements ControllerInterface
{
    /** @var string */
    public $db_host;
    /** @var string */
    public $db_name;
    /** @var string */
    public $db_pass;
    /** @var int */
    public $db_port;
    /** @var string */
    public $db_type;
    /** @var string */
    public $db_user;
    /** @var string */
    public $initial_pass;
    /** @var string */
    public $initial_user;
    /** @var Request */
    protected $request;
    /** @var bool */
    protected $use_new_mysql = false;

    /**
     * Constructor del controlador de instalación
     *
     * @param string $className Nombre de la clase (no utilizado)
     * @param string $url Ruta de la URL (no utilizado)
     */
    public function __construct(string $className, string $url = '')
    {
        $this->request = Request::createFromGlobals();
        $userLanguage = $this->request->get('erpia_lang', $this->detectUserLanguage());
        Helpers::getLanguageManager()->setDefaultLanguage($userLanguage);
        
        // Verificar si el sistema ya está instalado
        if (Config::get('db_name')) {
            throw new KernelException('SistemaYaInstalado', Helpers::translate('sistema-ya-instalado'));
        }
        
        Html::disablePlugins();
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
     * Ejecuta el proceso de instalación
     */
    public function run(): void
    {
        $this->db_host = strtolower(trim($this->request->get('erpia_db_host', 'localhost')));
        $this->db_name = trim($this->request->get('erpia_db_name', 'erpia'));
        $this->db_pass = $this->request->get('erpia_db_pass', '');
        $this->db_port = (int)$this->request->get('erpia_db_port', 3306);
        $this->db_type = $this->request->get('erpia_db_type', 'mysql');
        $this->db_user = trim($this->request->get('erpia_db_user', 'root'));
        $this->initial_user = trim($this->request->get('erpia_initial_user', ''));
        $this->initial_pass = $this->request->get('erpia_initial_pass', '');
        
        $installationSuccess = $this->validateRequirements() &&
            $this->request->method() === 'POST' &&
            $this->setupDatabase() &&
            $this->createDirectoryStructure() &&
            $this->generateHtaccessFile() &&
            $this->saveConfigurationFile();
        
        if ($installationSuccess) {
            if (!empty($this->request->get('unattended', ''))) {
                echo 'OK';
                return;
            }
            
            echo Html::render('Installer/Redirection.html.twig', [
                'initial_user' => empty($this->initial_user) ? 'admin' : $this->initial_user,
                'initial_pass' => empty($this->initial_pass) ? 'admin' : $this->initial_pass,
            ]);
            return;
        }
        
        if ($this->request->get('phpinfo', '') === 'TRUE') {
            phpinfo();
            return;
        }
        
        echo Html::render('Installer/Installation.html.twig', [
            'controller' => $this,
            'license' => file_get_contents(ERPIA_BASE_PATH . DIRECTORY_SEPARATOR . 'COPYING'),
            'timezones' => DateTimeZone::listIdentifiers(),
            'version' => Kernel::getVersion()
        ]);
    }

    /**
     * Configura y verifica la conexión a la base de datos
     *
     * @return bool
     */
    private function setupDatabase(): bool
    {
        $dbConfig = [
            'host' => $this->db_host,
            'port' => $this->db_port,
            'user' => $this->db_user,
            'pass' => $this->db_pass,
            'name' => $this->db_name,
            'socket' => $this->request->get('mysql_socket', ''),
            'pgsql-ssl' => $this->request->get('pgsql_ssl_mode', ''),
            'pgsql-endpoint' => $this->request->get('pgsql_endpoint', '')
        ];
        
        if ($this->db_type === 'postgresql' && strtolower($dbConfig['name']) !== $dbConfig['name']) {
            Helpers::logWarning('nombre-base-datos-minusculas');
            return false;
        }
        
        switch ($this->db_type) {
            case 'mysql':
                return $this->testMySQLConnection($dbConfig);
            case 'postgresql':
                return $this->testPostgreSQLConnection($dbConfig);
        }
        
        Helpers::logCritical('no-conexion-base-datos');
        return false;
    }

    /**
     * Crea la estructura de directorios necesaria
     *
     * @return bool
     */
    private function createDirectoryStructure(): bool
    {
        $requiredFolders = ['Plugins', 'Dinamic', 'MyFiles'];
        
        foreach ($requiredFolders as $folder) {
            $folderPath = ERPIA_BASE_PATH . DIRECTORY_SEPARATOR . $folder;
            if (file_exists($folderPath)) {
                continue;
            }
            
            if (mkdir($folderPath, 0755, true) === false) {
                Helpers::logCritical('error-crear-carpeta', ['%carpeta%' => $folder]);
                return false;
            }
        }
        
        Plugins::deploy();
        return true;
    }

    /**
     * Obtiene la URI base del sistema
     *
     * @return string
     */
    private function getBaseUri(): string
    {
        $uri = $this->request->getBasePath();
        return ('/' === substr($uri, -1)) ? substr($uri, 0, -1) : $uri;
    }

    /**
     * Detecta el idioma del usuario
     *
     * @return string
     */
    private function detectUserLanguage(): string
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $languageData = explode(';', $acceptLanguage);
        $primaryLanguage = str_replace('-', '_', explode(',', $languageData[0])[0]);
        
        $translationPath = ERPIA_BASE_PATH . '/Core/Translation/' . $primaryLanguage . '.json';
        return file_exists($translationPath) ? $primaryLanguage : 'en_EN';
    }

    /**
     * Genera el archivo .htaccess
     *
     * @return bool
     */
    private function generateHtaccessFile(): bool
    {
        $htaccessFile = fopen(ERPIA_BASE_PATH . '/.htaccess', 'wb');
        if ($htaccessFile === false) {
            Helpers::logCritical('error-guardar-htaccess');
            return false;
        }
        
        $samplePath = FileSystem::getPath(['htaccess-sample']);
        $fileContent = file_get_contents($samplePath);
        
        $baseRoute = $this->request->get('erpia_route', $this->getBaseUri());
        if (!empty($baseRoute)) {
            $fileContent = str_replace('RewriteBase /', 'RewriteBase ' . $baseRoute, $fileContent);
        }
        
        fwrite($htaccessFile, $fileContent);
        fclose($htaccessFile);
        return true;
    }

    /**
     * Guarda el archivo de configuración
     *
     * @return bool
     */
    private function saveConfigurationFile(): bool
    {
        $configFile = fopen(ERPIA_BASE_PATH . '/config.php', 'wb');
        if ($configFile === false) {
            Helpers::logCritical('error-guardar-configuracion');
            return false;
        }
        
        fwrite($configFile, "<?php\n");
        fwrite($configFile, "define('ERPIA_COOKIES_EXPIRE', " . $this->request->get('erpia_cookie_expire', 31536000) . ");\n");
        fwrite($configFile, "define('ERPIA_ROUTE', '" . $this->request->get('erpia_route', $this->getBaseUri()) . "');\n");
        fwrite($configFile, "define('ERPIA_DB_TYPE', '" . $this->db_type . "');\n");
        fwrite($configFile, "define('ERPIA_DB_HOST', '" . $this->db_host . "');\n");
        fwrite($configFile, "define('ERPIA_DB_PORT', " . $this->db_port . ");\n");
        fwrite($configFile, "define('ERPIA_DB_NAME', '" . $this->db_name . "');\n");
        fwrite($configFile, "define('ERPIA_DB_USER', '" . $this->db_user . "');\n");
        fwrite($configFile, "define('ERPIA_DB_PASS', '" . $this->db_pass . "');\n");
        fwrite($configFile, "define('ERPIA_DB_FOREIGN_KEYS', true);\n");
        fwrite($configFile, "define('ERPIA_DB_TYPE_CHECK', true);\n");
        
        if ($this->use_new_mysql) {
            fwrite($configFile, "define('ERPIA_MYSQL_CHARSET', 'utf8mb4');\n");
            fwrite($configFile, "define('ERPIA_MYSQL_COLLATE', 'utf8mb4_unicode_520_ci');\n");
        } elseif ($this->db_type === 'mysql') {
            fwrite($configFile, "define('ERPIA_MYSQL_CHARSET', 'utf8');\n");
            fwrite($configFile, "define('ERPIA_MYSQL_COLLATE', 'utf8_bin');\n");
        }
        
        if ($this->db_type === 'mysql' && $this->request->get('mysql_socket') !== '') {
            fwrite($configFile, "\nini_set('mysqli.default_socket', '" . $this->request->get('mysql_socket') . "');\n");
        } elseif ($this->db_type === 'postgresql') {
            fwrite($configFile, "define('ERPIA_PGSQL_SSL', '" . $this->request->get('pgsql_ssl_mode') . "');\n");
            fwrite($configFile, "define('ERPIA_PGSQL_ENDPOINT', '" . $this->request->get('pgsql_endpoint') . "');\n");
        }
        
        $textFields = [
            'lang' => 'es_ES',
            'timezone' => 'Europe/Madrid',
            'hidden_plugins' => ''
        ];
        
        foreach ($textFields as $field => $default) {
            fwrite($configFile, "define('ERPIA_" . strtoupper($field) . "', '" . $this->request->get('erpia_' . $field, $default) . "');\n");
        }
        
        $booleanFields = ['debug', 'disable_add_plugins', 'disable_rm_plugins'];
        foreach ($booleanFields as $field) {
            fwrite($configFile, "define('ERPIA_" . strtoupper($field) . "', " . $this->request->get('erpia_' . $field, 'false') . ");\n");
        }
        
        if ($this->request->get('erpia_gtm', false)) {
            fwrite($configFile, "define('GOOGLE_TAG_MANAGER', 'GTM-53H8T9BL');\n");
        }
        
        $initialUser = $this->request->get('erpia_initial_user', '');
        if (!empty($initialUser)) {
            fwrite($configFile, "define('ERPIA_INITIAL_USER', '" . $initialUser . "');\n");
        }
        
        $initialPass = $this->request->get('erpia_initial_pass', '');
        if (!empty($initialPass)) {
            fwrite($configFile, "define('ERPIA_INITIAL_PASS', '" . $initialPass . "');\n");
        }
        
        fclose($configFile);
        return true;
    }

    /**
     * Valida los requisitos del sistema
     *
     * @return bool
     */
    private function validateRequirements(): bool
    {
        $hasErrors = false;
        
        if ((float)'3,1' >= (float)'3.1') {
            Helpers::logCritical('separador-decimal-incorrecto');
            $hasErrors = true;
        }
        
        $requiredExtensions = ['bcmath', 'curl', 'fileinfo', 'gd', 'mbstring', 'openssl', 'simplexml', 'zip'];
        foreach ($requiredExtensions as $extension) {
            if (extension_loaded($extension) === false) {
                Helpers::logCritical('extension-php-no-encontrada', ['%extension%' => $extension]);
                $hasErrors = true;
            }
        }
        
        if (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules()) === false) {
            Helpers::logCritical('modulo-apache-no-encontrado', ['%modulo%' => 'mod_rewrite']);
            $hasErrors = true;
        }
        
        if (is_writable(ERPIA_BASE_PATH) === false) {
            Helpers::logCritical('carpeta-no-escribible');
            $hasErrors = true;
        }
        
        if (!empty($this->initial_user) && preg_match("/^[A-Z0-9_@\+\.\-]{3,50}$/i", $this->initial_user) !== 1) {
            Helpers::logWarning('usuario-admin-invalido', ['%min%' => '3', '%max%' => '50']);
            $hasErrors = true;
        }
        
        return $hasErrors === false;
    }

    /**
     * Prueba la conexión MySQL
     *
     * @param array $dbConfig Configuración de la base de datos
     * @return bool
     */
    private function testMySQLConnection(array $dbConfig): bool
    {
        if (class_exists('mysqli') === false) {
            Helpers::logCritical('extension-php-no-encontrada', ['%extension%' => 'mysqli']);
            return false;
        }
        
        if ($dbConfig['socket'] !== '') {
            ini_set('mysqli.default_socket', $dbConfig['socket']);
        }
        
        try {
            $connection = @new \mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], '', $dbConfig['port']);
            if ($connection->connect_error) {
                Helpers::logCritical('no-conexion-base-datos');
                Helpers::logCritical($connection->connect_errno . ': ' . $connection->connect_error);
                return false;
            }
        } catch (Exception $e) {
            Helpers::logCritical('no-conexion-base-datos');
            Helpers::logCritical($e->getMessage());
            return false;
        }
        
        if ($connection->server_version < 50700) {
            Helpers::logCritical('version-mysql-muy-antigua');
            return false;
        }
        
        $createDatabaseSQL = 'CREATE DATABASE IF NOT EXISTS ' . $connection->escape_string($dbConfig['name']) . ';';
        if ($connection->query($createDatabaseSQL) === false) {
            return false;
        }
        
        $serverVersion = $connection->server_version;
        $this->use_new_mysql = $serverVersion >= 100200 || ($serverVersion >= 80000 && $serverVersion < 100000);
        
        $showTablesSQL = 'SHOW TABLES FROM ' . $connection->escape_string($dbConfig['name']) . ';';
        $tablesResult = $connection->query($showTablesSQL);
        
        if ($tablesResult !== false) {
            while ($tableRow = $tablesResult->fetch_row()) {
                if ('usuarios' === $tableRow[0] || 'empresas' === $tableRow[0]) {
                    $this->use_new_mysql = false;
                    break;
                }
            }
        }
        
        return true;
    }

    /**
     * Prueba la conexión PostgreSQL
     *
     * @param array $dbConfig Configuración de la base de datos
     * @return bool
     */
    private function testPostgreSQLConnection(array $dbConfig): bool
    {
        if (function_exists('pg_connect') === false) {
            Helpers::logCritical('extension-php-no-encontrada', ['%extension%' => 'postgresql']);
            return false;
        }
        
        $connectionString = 'host=' . $dbConfig['host'] . ' port=' . $dbConfig['port'] .
            ' user=' . $dbConfig['user'] . ' password=' . $dbConfig['pass'];
        
        if ($dbConfig['pgsql-ssl'] !== '') {
            $connectionString .= ' sslmode=' . $dbConfig['pgsql-ssl'];
        }
        
        if ($dbConfig['pgsql-endpoint'] !== '') {
            $connectionString .= " options='endpoint=" . $dbConfig['pgsql-endpoint'] . "'";
        }
        
        $connection = @pg_connect($connectionString . ' dbname=' . $dbConfig['name']);
        if ($connection !== false) {
            if ($this->getPostgreSQLVersion($connection) < 10) {
                Helpers::logCritical('version-postgresql-muy-antigua');
                return false;
            }
            return true;
        }
        
        $connection = pg_connect($connectionString . ' dbname=postgres');
        if ($connection !== false) {
            if ($this->getPostgreSQLVersion($connection) < 10) {
                Helpers::logCritical('version-postgresql-muy-antigua');
                return false;
            }
            
            $createDatabaseSQL = 'CREATE DATABASE ' . pg_escape_string($connection, $dbConfig['name']) . ';';
            if (@pg_query($connection, $createDatabaseSQL) !== false) {
                return true;
            }
            
            if (pg_last_error($connection) !== false) {
                Helpers::logCritical(pg_last_error($connection));
                return false;
            }
            
            Helpers::logCritical('error-crear-base-datos');
            return false;
        }
        
        Helpers::logCritical('no-conexion-base-datos');
        if (is_resource($connection) && pg_last_error($connection) !== false) {
            Helpers::logCritical(pg_last_error($connection));
        }
        
        return false;
    }

    /**
     * Obtiene la versión de PostgreSQL
     *
     * @param resource $connection Conexión a PostgreSQL
     * @return float
     */
    private function getPostgreSQLVersion($connection): float
    {
        $versionInfo = pg_version($connection);
        $versionParts = explode(' ', $versionInfo['server']);
        return (float)$versionParts[0];
    }
}