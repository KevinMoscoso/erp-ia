<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de roles
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\Model\Role;
use ERPIA\Core\Model\User;
use ERPIA\Core\Helpers;
use ERPIA\Core\Where;
use ERPIA\Dinamic\Model\Page;
use ERPIA\Dinamic\Model\RoleAccess;

/**
 * Controlador para la edición de un registro del modelo Role
 */
class EditRole extends EditController
{
    /**
     * Obtiene las reglas de acceso para el rol actual
     *
     * @return array
     */
    public function getAccessRules(): array
    {
        $accessRules = [];
        
        foreach ($this->getAllSystemPages() as $systemPage) {
            $accessRules[$systemPage->name] = [
                'menu' => Helpers::translate($systemPage->menu),
                'submenu' => Helpers::translate($systemPage->submenu),
                'page' => Helpers::translate($systemPage->title),
                'show' => false,
                'onlyOwner' => false,
                'update' => false,
                'delete' => false
            ];
        }

        $roleCondition = [Where::equals('codrole', $this->getModel()->getId())];
        foreach (RoleAccess::getAll($roleCondition) as $roleAccess) {
            $accessRules[$roleAccess->pagename]['show'] = true;
            $accessRules[$roleAccess->pagename]['onlyOwner'] = $roleAccess->onlyownerdata;
            $accessRules[$roleAccess->pagename]['update'] = $roleAccess->allowupdate;
            $accessRules[$roleAccess->pagename]['delete'] = $roleAccess->allowdelete;
            $accessRules[$roleAccess->pagename]['export'] = $roleAccess->allowexport;
            $accessRules[$roleAccess->pagename]['import'] = $roleAccess->allowimport;
        }

        return $accessRules;
    }

    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Role';
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
        $pageInfo['title'] = 'rol';
        $pageInfo['icon'] = 'fa-solid fa-id-card';
        return $pageInfo;
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews(): void
    {
        parent::createViews();
        $this->configureTabPosition('bottom');

        // Desactivar botones no necesarios
        $mainView = $this->tab($this->getMainViewName());
        $mainView->setOption('btnOptions', false);
        $mainView->setOption('btnPrint', false);

        $this->createAccessRulesView();
        $this->createRoleUsersView();
    }

    /**
     * Crea la vista de reglas de acceso
     *
     * @param string $viewName
     */
    protected function createAccessRulesView(string $viewName = 'RoleAccess'): void
    {
        $this->addHtmlView($viewName, 'Tab/RoleAccess', 'RoleAccess', 'reglas', 'fa-solid fa-check-square');
    }

    /**
     * Crea la vista de usuarios del rol
     *
     * @param string $viewName
     */
    protected function createRoleUsersView(string $viewName = 'EditRoleUser'): void
    {
        $this->addEditListView($viewName, 'RoleUser', 'usuarios', 'fa-solid fa-address-card')
            ->disableColumn('rol', true)
            ->enableInlineEditing(true);
    }

    /**
     * Ejecuta la acción de edición de reglas
     *
     * @return bool
     */
    protected function editRulesAction(): bool
    {
        if ($this->userPermissions->allowUpdate === false) {
            Helpers::logWarning('no-permisos-actualizacion');
            return true;
        }
        
        if ($this->validateFormToken() === false) {
            return true;
        }

        $showPages = $this->request->getArray('show', false);
        $ownerOnlyPages = $this->request->getArray('onlyOwner', false);
        $updatePages = $this->request->getArray('update', false);
        $deletePages = $this->request->getArray('delete', false);
        $exportPages = $this->request->getArray('export', false);
        $importPages = $this->request->getArray('import', false);

        $roleCode = $this->request->get('code');
        $accessCondition = [Where::equals('codrole', $roleCode)];
        $existingRules = RoleAccess::getAll($accessCondition);
        
        foreach ($existingRules as $existingRule) {
            // Eliminar regla si no está en la selección
            if (is_array($showPages) === false || in_array($existingRule->pagename, $showPages) === false) {
                $existingRule->remove();
                continue;
            }

            // Actualizar regla existente
            $existingRule->onlyownerdata = is_array($ownerOnlyPages) && in_array($existingRule->pagename, $ownerOnlyPages);
            $existingRule->allowupdate = is_array($updatePages) && in_array($existingRule->pagename, $updatePages);
            $existingRule->allowdelete = is_array($deletePages) && in_array($existingRule->pagename, $deletePages);
            $existingRule->allowexport = is_array($exportPages) && in_array($existingRule->pagename, $exportPages);
            $existingRule->allowimport = is_array($importPages) && in_array($existingRule->pagename, $importPages);
            $existingRule->store();
        }

        // Añadir nuevas reglas
        if (is_array($showPages)) {
            foreach ($showPages as $pageName) {
                $ruleExists = false;
                
                foreach ($existingRules as $existingRule) {
                    if ($existingRule->pagename === $pageName) {
                        $ruleExists = true;
                        break;
                    }
                }
                
                if ($ruleExists) {
                    continue;
                }

                $newAccessRule = new RoleAccess();
                $newAccessRule->codrole = $roleCode;
                $newAccessRule->pagename = $pageName;
                $newAccessRule->onlyownerdata = is_array($ownerOnlyPages) && in_array($pageName, $ownerOnlyPages);
                $newAccessRule->allowupdate = is_array($updatePages) && in_array($pageName, $updatePages);
                $newAccessRule->allowdelete = is_array($deletePages) && in_array($pageName, $deletePages);
                $newAccessRule->allowexport = is_array($exportPages) && in_array($pageName, $exportPages);
                $newAccessRule->allowimport = is_array($importPages) && in_array($pageName, $importPages);
                $newAccessRule->store();
            }
        }

        $this->cleanOrphanedAccess();
        Helpers::logNotice('registro-actualizado-correctamente');
        return true;
    }

    /**
     * Ejecuta acciones previas personalizadas
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction(string $action): bool
    {
        if ($action === 'edit-rules') {
            return $this->editRulesAction();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Obtiene todas las páginas del sistema
     *
     * @return array
     */
    protected function getAllSystemPages(): array
    {
        $ordering = ['menu' => 'ASC', 'submenu' => 'ASC', 'title' => 'ASC'];
        return Page::getAll([], $ordering);
    }

    /**
     * Carga datos en una vista específica
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData(string $viewName, BaseView $view): void
    {
        switch ($viewName) {
            case 'EditRoleUser':
                $roleCode = $this->getViewModelValue($this->getMainViewName(), 'codrole');
                $filterCondition = [new DataBaseWhere('codrole', $roleCode)];
                $view->loadData('', $filterCondition, ['id' => 'DESC']);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Elimina permisos huérfanos
     */
    protected function cleanOrphanedAccess(): void
    {
        $systemPages = Page::getAll();
        $allAccessRules = RoleAccess::getAll();
        
        $pageNames = array_column($systemPages, 'name');
        $accessPageNames = array_column($allAccessRules, 'pagename');
        
        $orphanedPages = array_diff($accessPageNames, $pageNames);
        
        foreach ($orphanedPages as $orphanedPageName) {
            $orphanedAccess = new RoleAccess();
            $orphanedAccess->loadWhere([new DataBaseWhere('pagename', $orphanedPageName)]);
            $orphanedAccess->remove();

            $roleAccessCount = RoleAccess::count([Where::equals('codrole', $orphanedAccess->codrole)]);
            
            if ($roleAccessCount === 0) {
                $emptyRole = new Role();
                $emptyRole->loadWhere([new DataBaseWhere('codrole', $orphanedAccess->codrole)]);
                $emptyRole->remove();

                $userModel = new User();
                $this->redirect($userModel->getUrl() . '?activetab=ListRole');
            }
        }
    }
}