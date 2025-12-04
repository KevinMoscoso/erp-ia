<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de usuarios
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\Helpers;
use ERPIA\Core\Config;
use ERPIA\Core\Where;
use ERPIA\Dinamic\Model\Warehouse;
use ERPIA\Dinamic\Model\Page;
use ERPIA\Dinamic\Model\RoleUser;
use ERPIA\Dinamic\Model\User;

/**
 * Controlador para la edición de un registro del modelo User
 */
class EditUser extends EditController
{
    /**
     * Obtiene la URL de la imagen del usuario
     *
     * @return string
     */
    public function getImageUrl(): string
    {
        return $this->getModel()->getGravatarUrl();
    }

    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'User';
    }

    /**
     * Obtiene los datos de configuración de la página
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageInfo = parent::getPageData();
        $pageInfo['menu'] = 'administracion';
        $pageInfo['title'] = 'usuario';
        $pageInfo['icon'] = 'fa-solid fa-user-circle';
        return $pageInfo;
    }

    /**
     * Verifica si el usuario actual puede actualizar el registro
     *
     * @return bool
     */
    private function checkUpdatePermission(): bool
    {
        $userModel = new User();
        $userCode = $this->request->get('code');
        
        if ($userModel->load($userCode) === false) {
            return $this->currentUser->admin;
        }
        
        if ($this->currentUser->admin) {
            return true;
        }
        
        return $userModel->nick === $this->currentUser->nick;
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews(): void
    {
        parent::createViews();
        $this->configureTabPosition('top');
        
        if ($this->currentUser->admin) {
            $this->createRolesView();
        }
        
        $this->createPageOptionsView();
        $this->createEmailsView();
    }

    /**
     * Crea vista de correos enviados
     *
     * @param string $viewName
     */
    protected function createEmailsView(string $viewName = 'ListEmailSent'): void
    {
        $this->addListView($viewName, 'EmailSent', 'correos-enviados', 'fa-solid fa-envelope')
            ->addOrderBy(['fecha'], 'fecha', 2)
            ->addSearchFields(['destinatario', 'cuerpo', 'asunto'])
            ->disableColumn('usuario')
            ->setOption('btnNew', false)
            ->addFilterPeriod('periodo', 'fecha', 'fecha', true);
    }

    /**
     * Crea vista de opciones de página
     *
     * @param string $viewName
     */
    protected function createPageOptionsView(string $viewName = 'ListPageOption'): void
    {
        $this->addListView($viewName, 'PageOption', 'opciones-pagina', 'fa-solid fa-wrench')
            ->addOrderBy(['nombre'], 'nombre', 1)
            ->addOrderBy(['ultima_actualizacion'], 'ultima-actualizacion')
            ->addSearchFields(['nombre'])
            ->setOption('btnNew', false);
    }

    /**
     * Crea vista de roles de usuario
     *
     * @param string $viewName
     */
    protected function createRolesView(string $viewName = 'EditRoleUser'): void
    {
        $this->addEditListView($viewName, 'RoleUser', 'roles', 'fa-solid fa-address-card')
            ->enableInlineEditing(true)
            ->disableColumn('usuario', true);
    }

    /**
     * Ejecuta acción de eliminación
     *
     * @return bool
     */
    protected function deleteAction(): bool
    {
        $this->userPermissions->allowDelete = $this->currentUser->admin;
        return parent::deleteAction();
    }

    /**
     * Ejecuta acción de edición
     *
     * @return bool
     */
    protected function editAction(): bool
    {
        $this->userPermissions->allowUpdate = $this->checkUpdatePermission();
        
        if ($this->request->get('code', '') === $this->currentUser->nick) {
            if ($this->currentUser->admin != (bool)$this->request->get('admin')) {
                $this->userPermissions->allowUpdate = false;
            } elseif ($this->currentUser->enabled != (bool)$this->request->get('enabled')) {
                $this->userPermissions->allowUpdate = false;
            }
        }
        
        $result = parent::editAction();
        
        if ($result && $this->tab('EditUser')->model->nick === $this->currentUser->nick) {
            $languageManager = Helpers::getLanguageManager();
            $languageManager->setCurrentLanguage($this->tab('EditUser')->model->langcode);
            
            $cookieExpiry = time() + Config::get('cookies_expire');
            $this->response->setCookie('erpiaLang', $this->tab('EditUser')->model->langcode, $cookieExpiry);
        }
        
        return $result;
    }

    /**
     * Ejecuta acciones posteriores
     *
     * @param string $action
     */
    protected function execAfterAction(string $action): void
    {
        switch ($action) {
            case 'two-factor-enable':
                $this->enableTwoFactorAction();
                return;
        }
        parent::execAfterAction($action);
    }

    /**
     * Ejecuta acciones previas personalizadas
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction(string $action): bool
    {
        switch ($action) {
            case 'two-factor-disable':
                $this->disableTwoFactorAction();
                return true;
            case 'two-factor-verify':
                $this->verifyTwoFactorAction();
                return true;
        }
        return parent::execPreviousAction($action);
    }

    /**
     * Obtiene las páginas a las que tiene acceso un usuario
     *
     * @param User $user
     * @return array
     */
    protected function getUserPages(User $user): array
    {
        $accessiblePages = [];
        
        if ($user->admin) {
            foreach (Page::getAll([], ['nombre' => 'ASC']) as $page) {
                if ($page->showonmenu === false) {
                    continue;
                }
                $accessiblePages[] = ['value' => $page->nombre, 'title' => $page->nombre];
            }
            return $accessiblePages;
        }
        
        $userCondition = [Where::equals('nick', $user->nick)];
        foreach (RoleUser::getAll($userCondition) as $userRole) {
            foreach ($userRole->getRoleAccessList() as $roleAccess) {
                $page = $roleAccess->getPage();
                if ($page->exists() === false || $page->showonmenu === false) {
                    continue;
                }
                $accessiblePages[$roleAccess->pagename] = [
                    'value' => $roleAccess->pagename, 
                    'title' => $roleAccess->pagename
                ];
            }
        }
        
        return $accessiblePages;
    }

    /**
     * Ejecuta acción de inserción
     *
     * @return bool
     */
    protected function insertAction(): bool
    {
        $this->userPermissions->allowUpdate = $this->currentUser->admin;
        return parent::insertAction();
    }

    /**
     * Carga datos en una vista específica
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData(string $viewName, BaseView $view): void
    {
        $mainView = $this->getMainViewName();
        $userNick = $this->getViewModelValue($mainView, 'nick');
        
        switch ($viewName) {
            case 'EditRoleUser':
                $filterCondition = [new DataBaseWhere('nick', $userNick)];
                $view->loadData('', $filterCondition, ['id' => 'DESC']);
                break;
                
            case 'EditUser':
                parent::loadData($viewName, $view);
                
                if ($this->checkUpdatePermission() === false) {
                    $this->setTemplate('Error/AccessDenied');
                    break;
                }
                
                $this->loadHomepageOptions();
                $this->loadLanguageOptions();
                
                if ($this->companyModel->totalCount() < 2) {
                    $view->disableColumn('empresa');
                }
                
                $warehouseModel = new Warehouse();
                if ($warehouseModel->totalCount() < 2) {
                    $view->disableColumn('almacen');
                }
                
                $view->setOption('btnOptions', false)
                     ->setOption('btnPrint', false);
                
                if ($view->model->nick === $this->currentUser->nick) {
                    $view->setOption('btnDelete', false);
                }
                
                if ($view->model->two_factor_enabled) {
                    $this->addCustomButton($viewName, [
                        'action' => 'two-factor-disable',
                        'color' => 'warning',
                        'confirm' => true,
                        'icon' => 'fa-solid fa-shield-halved',
                        'label' => 'deshabilitar-dos-factores',
                    ]);
                } else {
                    $this->addCustomButton($viewName, [
                        'action' => 'two-factor-enable',
                        'color' => 'info',
                        'icon' => 'fa-solid fa-shield-halved',
                        'label' => 'habilitar-dos-factores',
                    ]);
                }
                
                if ($view->model->admin && isset($this->views['EditRoleUser'])) {
                    $this->configureViewOption('EditRoleUser', 'active', false);
                }
                break;
                
            case 'ListEmailSent':
                $filterCondition = [new DataBaseWhere('nick', $userNick)];
                $view->loadData('', $filterCondition);
                break;
                
            case 'ListPageOption':
                $filterCondition = [
                    new DataBaseWhere('nick', $userNick),
                    new DataBaseWhere('nick', null, 'IS', 'OR'),
                ];
                $view->loadData('', $filterCondition);
                break;
        }
    }

    /**
     * Carga opciones para la página de inicio
     */
    protected function loadHomepageOptions(): void
    {
        if ($this->tab('EditUser')->model->exists() === false) {
            $this->tab('EditUser')->disableColumn('pagina_inicio');
            return;
        }
        
        $homepageColumn = $this->tab('EditUser')->getColumn('pagina_inicio');
        if ($homepageColumn && $homepageColumn->widget->getType() === 'select') {
            $userPages = $this->getUserPages($this->tab('EditUser')->model);
            $homepageColumn->widget->setOptionsFromArray($userPages, false, true);
        }
    }

    /**
     * Carga opciones de idioma disponibles
     */
    protected function loadLanguageOptions(): void
    {
        $languageColumn = $this->tab('EditUser')->getColumn('language');
        if ($languageColumn && $languageColumn->widget->getType() === 'select') {
            $languageManager = Helpers::getLanguageManager();
            $availableLanguages = [];
            
            foreach ($languageManager->getAvailableLanguages() as $code => $name) {
                $availableLanguages[] = ['value' => $code, 'title' => $name];
            }
            
            $languageColumn->widget->setOptionsFromArray($availableLanguages, false);
        }
    }

    /**
     * Ejecuta acción para deshabilitar autenticación de dos factores
     */
    protected function disableTwoFactorAction(): void
    {
        if ($this->checkUpdatePermission() === false) {
            Helpers::logWarning('no-permisos-actualizacion');
            return;
        }
        
        if ($this->validateFormToken() === false) {
            return;
        }
        
        $userModel = new User();
        $userCode = $this->request->get('code');
        
        if ($userModel->load($userCode) === false) {
            Helpers::logError('registro-no-encontrado');
            return;
        }
        
        if ($userModel->disableTwoFactorAuth() === false) {
            Helpers::logError('error-deshabilitar-dos-factores');
            return;
        }
        
        if ($userModel->store() === false) {
            Helpers::logError('error-guardar-registro');
            return;
        }
        
        Helpers::logNotice('autenticacion-dos-factores-deshabilitada');
    }

    /**
     * Ejecuta acción para habilitar autenticación de dos factores
     */
    protected function enableTwoFactorAction(): void
    {
        if ($this->checkUpdatePermission() === false) {
            Helpers::logWarning('no-permisos-actualizacion');
            return;
        }
        
        if ($this->validateFormToken() === false) {
            return;
        }
        
        $userModel = $this->getModel();
        if ($userModel->exists() === false) {
            Helpers::logError('registro-no-encontrado');
            return;
        }
        
        if (empty($userModel->enableTwoFactorAuth())) {
            Helpers::logError('error-habilitar-dos-factores');
            return;
        }
        
        $this->setTemplate('EditUserTwoFactor');
    }

    /**
     * Ejecuta acción para verificar autenticación de dos factores
     */
    protected function verifyTwoFactorAction(): void
    {
        if ($this->checkUpdatePermission() === false) {
            Helpers::logWarning('no-permisos-actualizacion');
            return;
        }
        
        if ($this->validateFormToken() === false) {
            return;
        }
        
        $userModel = new User();
        $userCode = $this->request->get('code');
        
        if ($userModel->load($userCode) === false) {
            Helpers::logError('registro-no-encontrado');
            return;
        }
        
        $secretKey = $this->request->get('two_factor_secret_key', '');
        if (empty($userModel->enableTwoFactorAuth($secretKey))) {
            Helpers::logError('clave-secreta-dos-factores-vacia');
            return;
        }
        
        $verificationCode = $this->request->get('two_factor_code', '');
        if ($userModel->verifyTwoFactorCode($verificationCode) === false) {
            Helpers::logError('codigo-dos-factores-invalido');
            return;
        }
        
        if ($userModel->store() === false) {
            Helpers::logError('error-guardar-registro');
            return;
        }
        
        Helpers::logNotice('autenticacion-dos-factores-habilitada');
    }
}