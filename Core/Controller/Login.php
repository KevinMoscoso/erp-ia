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

use ERPIA\Core\Cache;
use ERPIA\Core\Contract\ControllerInterface;
use ERPIA\Core\DataSrc\Empresas;
use ERPIA\Core\Html;
use ERPIA\Core\Lib\MultiRequestProtection;
use ERPIA\Core\Request;
use ERPIA\Core\Session;
use ERPIA\Core\Translator;
use ERPIA\Dinamic\Model\Empresa;
use ERPIA\Dinamic\Model\User;

/**
 * Controlador de autenticación del sistema ERPIA
 * 
 * Gestiona inicio de sesión, cierre de sesión, validación de dos factores,
 * cambio de contraseña y protección contra ataques por fuerza bruta.
 */
class Login implements ControllerInterface
{
    const INCIDENT_EXPIRATION_TIME = 600;
    const IP_LIST = 'login-ip-list';
    const MAX_INCIDENT_COUNT = 6;
    const USER_LIST = 'login-user-list';

    /** @var Empresa */
    public $empresa;

    /** @var string */
    private $template = 'Login/Login.html.twig';

    /** @var string */
    public $title = 'Login';

    /** @var string */
    public $two_factor_user;

    public function __construct(string $className, string $url = '')
    {
        // Constructor vacío, se mantiene por compatibilidad con la interfaz
    }

    /**
     * Limpia todos los incidentes registrados en caché
     */
    public function clearIncidents(): void
    {
        Cache::delete(self::IP_LIST);
        Cache::delete(self::USER_LIST);
    }

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Array vacío ya que el login no requiere metadatos específicos
     */
    public function getPageData(): array
    {
        return [];
    }

    /**
     * Punto de entrada principal del controlador
     */
    public function run(): void
    {
        $this->empresa = Empresas::default();
        $this->title = $this->empresa->nombrecorto;
        
        $request = Request::createFromGlobals();
        $action = $request->inputOrQuery('action', '');
        
        switch ($action) {
            case 'change-password':
                $this->changePasswordAction($request);
                break;
            case 'login':
                $this->loginAction($request);
                break;
            case 'logout':
                $this->logoutAction($request);
                break;
            case 'two-factor-validation':
                $this->twoFactorValidationAction($request);
                break;
        }
        
        echo Html::render($this->template, [
            'controllerName' => 'Login',
            'debugBarRender' => false,
            'fsc' => $this,
            'template' => $this->template,
        ]);
    }

    /**
     * Registra un incidente de seguridad
     * 
     * @param string $ip Dirección IP del incidente
     * @param string $user Nombre de usuario (opcional)
     * @param int|null $time Marca de tiempo del incidente (opcional)
     */
    public function saveIncident(string $ip, string $user = '', ?int $time = null): void
    {
        // Agregar IP actual a la lista
        $ipList = $this->getIpList();
        $ipList[] = [
            'ip' => $ip,
            'time' => ($time ?? time())
        ];
        Cache::set(self::IP_LIST, $ipList);

        // Si el usuario no está vacío, guardar incidente de usuario
        if (empty($user)) {
            return;
        }

        // Agregar usuario actual a la lista
        $userList = $this->getUserList();
        $userList[] = [
            'user' => $user,
            'time' => ($time ?? time())
        ];
        Cache::set(self::USER_LIST, $userList);
    }

    /**
     * Verifica si una IP o usuario tiene muchos incidentes
     * 
     * @param string $ip Dirección IP a verificar
     * @param string $username Nombre de usuario a verificar
     * @return bool True si excede el límite de incidentes
     */
    public function userHasManyIncidents(string $ip, string $username = ''): bool
    {
        // Contar incidentes por IP
        $ipCount = 0;
        foreach ($this->getIpList() as $item) {
            if ($item['ip'] === $ip) {
                $ipCount++;
            }
        }
        
        if ($ipCount >= self::MAX_INCIDENT_COUNT) {
            return true;
        }

        // Contar incidentes por usuario
        $userCount = 0;
        foreach ($this->getUserList() as $item) {
            if ($item['user'] === $username) {
                $userCount++;
            }
        }
        
        return $userCount >= self::MAX_INCIDENT_COUNT;
    }

    /**
     * Procesa la acción de cambio de contraseña
     * 
     * @param Request $request Objeto Request con los datos del formulario
     */
    protected function changePasswordAction(Request $request): void
    {
        if (false === $this->validateFormToken($request)) {
            return;
        }

        $username = $request->input('fsNewUserPasswd');
        if ($this->userHasManyIncidents(Session::getClientIp(), $username)) {
            Translator::log()->warning('ip-banned');
            return;
        }

        $dbPassword = $request->input('fsDbPasswd');
        $config = \ERPIA\Core\Config::getInstance();
        if ($dbPassword !== $config->get('db_pass')) {
            Translator::log()->warning('login-invalid-db-password');
            $this->saveIncident(Session::getClientIp(), $username);
            return;
        }

        $password = $request->input('fsNewPasswd');
        $password2 = $request->input('fsNewPasswd2');
        if (empty($username) || empty($password) || empty($password2)) {
            Translator::log()->warning('login-empty-fields');
            return;
        }

        if ($password !== $password2) {
            Translator::log()->warning('different-passwords', ['%userNick%' => $username]);
            return;
        }

        $user = new User();
        if (false === $user->load($username)) {
            Translator::log()->warning('login-user-not-found');
            $this->saveIncident(Session::getClientIp(), $username);
            return;
        }

        if (false === $user->enabled) {
            Translator::log()->warning('login-user-disabled');
            return;
        }

        $user->setPassword($password);
        // Desactivar 2FA si estaba activado
        if ($user->two_factor_enabled) {
            $user->disableTwoFactor();
        }

        if (false === $user->save()) {
            Translator::log()->warning('login-user-not-saved');
            $this->saveIncident(Session::getClientIp(), $username);
            return;
        }

        Translator::log()->notice('login-password-changed');
    }

    /**
     * Valida el token del formulario para prevenir CSRF
     * 
     * @param Request $request Objeto Request con el token
     * @return bool True si el token es válido
     */
    protected function validateFormToken(Request $request): bool
    {
        $multiRequestProtection = new MultiRequestProtection();
        
        // Si el usuario está autenticado, añadimos su nick a la semilla
        $cookieNick = $request->cookie('fsNick', '');
        if ($cookieNick) {
            $multiRequestProtection->addSeed($cookieNick);
        }

        // Comprobar el token
        $token = $request->inputOrQuery('multireqtoken', '');
        if (empty($token) || false === $multiRequestProtection->validate($token)) {
            Translator::log()->warning('invalid-request');
            return false;
        }

        // Comprobar que el token no se haya usado antes
        if ($multiRequestProtection->tokenExist($token)) {
            Translator::log()->warning('duplicated-request');
            return false;
        }

        return true;
    }

    /**
     * Obtiene la lista de incidentes por IP filtrada por expiración
     * 
     * @return array Lista de incidentes de IP
     */
    protected function getIpList(): array
    {
        $ipList = Cache::get(self::IP_LIST);
        if (false === is_array($ipList)) {
            return [];
        }

        // Eliminar elementos expirados
        $newList = [];
        foreach ($ipList as $item) {
            if (time() - $item['time'] < self::INCIDENT_EXPIRATION_TIME) {
                $newList[] = $item;
            }
        }

        return $newList;
    }

    /**
     * Obtiene la lista de incidentes por usuario filtrada por expiración
     * 
     * @return array Lista de incidentes de usuario
     */
    protected function getUserList(): array
    {
        $userList = Cache::get(self::USER_LIST);
        if (false === is_array($userList)) {
            return [];
        }

        // Eliminar elementos expirados
        $newList = [];
        foreach ($userList as $item) {
            if (time() - $item['time'] < self::INCIDENT_EXPIRATION_TIME) {
                $newList[] = $item;
            }
        }

        return $newList;
    }

    /**
     * Procesa la acción de inicio de sesión
     * 
     * @param Request $request Objeto Request con las credenciales
     */
    protected function loginAction(Request $request): void
    {
        if (false === $this->validateFormToken($request)) {
            return;
        }

        $userName = $request->input('fsNick');
        $password = $request->input('fsPassword');
        if (empty($userName) || empty($password)) {
            Translator::log()->warning('login-error-empty-fields');
            return;
        }

        // Verificar si el usuario está en la lista de incidentes
        if ($this->userHasManyIncidents(Session::getClientIp(), $userName)) {
            Translator::log()->warning('ip-banned');
            return;
        }

        $user = new User();
        if (false === $user->load($userName)) {
            Translator::log()->warning('login-user-not-found', ['%nick%' => htmlspecialchars($userName)]);
            $this->saveIncident(Session::getClientIp());
            return;
        }

        if (false === $user->enabled) {
            Translator::log()->warning('login-user-disabled');
            return;
        }

        if (false === $user->verifyPassword($password)) {
            Translator::log()->warning('login-password-fail');
            $this->saveIncident(Session::getClientIp(), $userName);
            return;
        }

        if ($user->two_factor_enabled) {
            $this->two_factor_user = $user->nick;
            $this->template = 'Login/TwoFactor.html.twig';
            return;
        }

        $this->updateUserAndRedirect($user, Session::getClientIp(), $request);
    }

    /**
     * Procesa la validación de autenticación de dos factores
     * 
     * @param Request $request Objeto Request con el código 2FA
     */
    protected function twoFactorValidationAction(Request $request): void
    {
        $user = new User();
        if (!$user->load($request->input('fsNick'))) {
            Translator::log()->warning('user-not-found');
            $this->saveIncident(Session::getClientIp());
            return;
        }

        if (!$user->verifyTwoFactorCode($request->input('fsTwoFactorCode'))) {
            Translator::log()->warning('two-factor-code-invalid');
            $this->saveIncident(Session::getClientIp(), $user->nick);
            return;
        }

        $this->updateUserAndRedirect($user, Session::getClientIp(), $request);
    }

    /**
     * Actualiza los datos del usuario y redirige a su página de inicio
     * 
     * @param User $user Usuario autenticado
     * @param string $ip Dirección IP del cliente
     * @param Request $request Objeto Request
     */
    protected function updateUserAndRedirect(User $user, string $ip, Request $request): void
    {
        // Actualizar datos del usuario
        Session::set('user', $user);
        $browser = $request->userAgent();
        $user->newLogkey($ip, $browser);
        
        if (false === $user->save()) {
            Translator::log()->warning('login-user-not-saved');
            return;
        }

        // Guardar cookies
        $this->saveCookies($user, $request);

        // Redirigir a la página de inicio del usuario
        $config = \ERPIA\Core\Config::getInstance();
        if (empty($user->homepage)) {
            $user->homepage = $config->get('route') . '/';
        }
        
        header('Location: ' . $user->homepage);
        exit;
    }

    /**
     * Procesa la acción de cierre de sesión
     * 
     * @param Request $request Objeto Request
     */
    protected function logoutAction(Request $request): void
    {
        if (false === $this->validateFormToken($request)) {
            return;
        }

        // Eliminar cookies
        $config = \ERPIA\Core\Config::getInstance();
        $path = $config->get('route', '/');
        
        setcookie('fsNick', '', time() - 3600, $path);
        setcookie('fsLogkey', '', time() - 3600, $path);
        setcookie('fsLang', '', time() - 3600, $path);

        // Reiniciar token
        $multiRequestProtection = new MultiRequestProtection();
        $multiRequestProtection->clearSeed();
        
        Translator::log()->notice('logout-ok');
    }

    /**
     * Establece las cookies de sesión del usuario
     * 
     * @param User $user Usuario autenticado
     * @param Request $request Objeto Request
     */
    protected function saveCookies(User $user, Request $request): void
    {
        $config = \ERPIA\Core\Config::getInstance();
        $expiration = time() + (int)$config->get('cookies_expire', 31536000);
        $path = $config->get('route', '/');
        $secure = $request->isSecure();
        
        setcookie('fsNick', $user->nick, $expiration, $path, '', $secure, true);
        setcookie('fsLogkey', $user->logkey, $expiration, $path, '', $secure, true);
        setcookie('fsLang', $user->langcode, $expiration, $path, '', $secure, true);
    }
}