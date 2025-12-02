<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\DocFilesTrait;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\SystemTools;
use ERPIA\Dinamic\Model\Contacto;
use ERPIA\Dinamic\Model\RoleAccess;

/**
 * Controller to edit a single item from the Contacto model
 *
 * @author ERPIA Team
 */
class EditContacto extends EditController
{
    use DocFilesTrait;

    private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    /**
     * Returns the contact's Gravatar image URL
     * @return string
     */
    public function getImageUrl(): string
    {
        $mainView = $this->getMainViewName();
        return $this->views[$mainView]->model->gravatar();
    }

    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Contacto';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'sales';
        $pageConfig['title'] = 'contact';
        $pageConfig['icon'] = 'fa-solid fa-address-book';
        return $pageConfig;
    }

    /**
     * Adds conversion buttons (to customer/supplier) to the view
     * @param string $viewName
     * @param BaseView $view
     */
    protected function addConversionButtons(string $viewName, BaseView $view): void
    {
        $clientPermissions = $this->getRolePermissions('EditCliente');
        if (empty($view->model->codcliente) && $clientPermissions['allowupdate']) {
            $this->addButton($viewName, [
                'action' => 'convert-into-customer',
                'color' => 'success',
                'icon' => 'fa-solid fa-user-check',
                'label' => 'convert-into-customer'
            ]);
        }

        $supplierPermissions = $this->getRolePermissions('EditProveedor');
        if (empty($view->model->codproveedor) && $supplierPermissions['allowupdate']) {
            $this->addButton($viewName, [
                'action' => 'convert-into-supplier',
                'color' => 'success',
                'icon' => 'fa-solid fa-user-cog',
                'label' => 'convert-into-supplier'
            ]);
        }
    }

    /**
     * Handles the VIES check action
     * @return bool
     */
    protected function checkViesAction(): bool
    {
        $model = $this->getModel();
        $code = $this->request->input('code');
        if (!$model->loadFromCode($code)) {
            return true;
        }

        $model->checkVies();
        return true;
    }

    /**
     * Creates a customer from the contact
     */
    protected function createCustomerAction(): void
    {
        $permissions = $this->getRolePermissions('EditCliente');
        if (!$permissions['allowupdate']) {
            SystemTools::log()->warning('not-allowed-update');
            return;
        }

        $mainView = $this->getMainViewName();
        $customer = $this->views[$mainView]->model->getCustomer();
        if ($customer->exists()) {
            SystemTools::log()->notice('record-updated-correctly');
            $this->redirect($customer->url() . '&action=save-ok');
            return;
        }

        SystemTools::log()->error('record-save-error');
    }

    /**
     * Creates the emails sent view
     * @param string $viewName
     */
    protected function createEmailsView(string $viewName = 'ListEmailSent'): void
    {
        $this->addListView($viewName, 'EmailSent', 'emails-sent', 'fa-solid fa-envelope')
            ->addOrderBy(['date'], 'date', 2)
            ->addSearchFields(['addressee', 'body', 'subject'])
            ->disableColumn('to')
            ->setSettings('btnNew', false);
    }

    /**
     * Creates the estimations view
     * @param string $viewName
     */
    protected function createEstimationsView(string $viewName = 'ListPresupuestoCliente'): void
    {
        $this->addListView($viewName, 'PresupuestoCliente', 'estimations', 'fa-solid fa-copy')
            ->addOrderBy(['fecha'], 'date', 2)
            ->addSearchFields(['codigo', 'numero2', 'observaciones']);
    }

    /**
     * Creates a supplier from the contact
     */
    protected function createSupplierAction(): void
    {
        $permissions = $this->getRolePermissions('EditProveedor');
        if (!$permissions['allowupdate']) {
            SystemTools::log()->warning('not-allowed-update');
            return;
        }

        $mainView = $this->getMainViewName();
        $supplier = $this->views[$mainView]->model->getSupplier();
        if ($supplier->exists()) {
            SystemTools::log()->notice('record-updated-correctly');
            $this->redirect($supplier->url() . '&action=save-ok');
            return;
        }

        SystemTools::log()->error('record-save-error');
    }

    /**
     * Creates all views for the controller
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createEmailsView();
        $this->createViewDocFiles();

        if ($this->user->can('EditPresupuestoCliente')) {
            $this->createEstimationsView();
        }
    }

    /**
     * Handles edit action with relation updates
     * @return bool
     */
    protected function editAction(): bool
    {
        $result = parent::editAction();
        if ($result && $this->active === $this->getMainViewName()) {
            $this->updateRelations($this->views[$this->active]->model);
        }
        return $result;
    }

    /**
     * Executes actions after the main action
     * @param string $action
     */
    protected function execAfterAction($action)
    {
        switch ($action) {
            case 'convert-into-customer':
                $this->createCustomerAction();
                break;

            case 'convert-into-supplier':
                $this->createSupplierAction();
                break;

            default:
                parent::execAfterAction($action);
        }
    }

    /**
     * Executes actions before data reading
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action): bool
    {
        switch ($action) {
            case 'add-file':
                return $this->addFileAction();

            case 'check-vies':
                return $this->checkViesAction();

            case 'delete-file':
                return $this->deleteFileAction();

            case 'edit-file':
                return $this->editFileAction();

            case 'unlink-file':
                return $this->unlinkFileAction();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Gets role permissions for a specific page
     * @param string $pageName
     * @return array
     */
    protected function getRolePermissions(string $pageName): array
    {
        $access = [
            'allowdelete' => $this->user->admin,
            'allowupdate' => $this->user->admin,
            'onlyownerdata' => $this->user->admin
        ];
        foreach (RoleAccess::allFromUser($this->user->nick, $pageName) as $rolePermission) {
            if ($rolePermission->allowdelete) {
                $access['allowdelete'] = true;
            }
            if ($rolePermission->allowupdate) {
                $access['allowupdate'] = true;
            }
            if ($rolePermission->onlyownerdata) {
                $access['onlyownerdata'] = true;
            }
        }
        return $access;
    }

    /**
     * Loads data for each view
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainView = $this->getMainViewName();

        switch ($viewName) {
            case 'docfiles':
                $this->loadDataDocFiles($view, $this->getModelClassName(), $this->getModel()->id());
                break;

            case 'ListEmailSent':
                $contactEmail = $this->getViewModelValue($mainView, 'email');
                if (empty($contactEmail)) {
                    $this->setSettings($viewName, 'active', false);
                    break;
                }

                $conditions = [new DataBaseWhere('addressee', $contactEmail)];
                $view->loadData('', $conditions);

                $this->addButton($viewName, [
                    'action' => 'SendMail?email=' . $contactEmail,
                    'color' => 'success',
                    'icon' => 'fa-solid fa-envelope',
                    'label' => 'send',
                    'type' => 'link'
                ]);
                break;

            case 'ListPresupuestoCliente':
                $contactId = $this->getViewModelValue($mainView, 'idcontacto');
                $conditions = [new DataBaseWhere('idcontactofact', $contactId)];
                $view->loadData('', $conditions);
                break;

            case $mainView:
                parent::loadData($viewName, $view);
                $this->loadLanguageOptions($viewName);
                if (!$view->model->exists()) {
                    break;
                }
                if ($this->permissions->allowUpdate) {
                    $this->addConversionButtons($viewName, $view);
                }
                if (!empty($view->model->cifnif)) {
                    $this->addButton($viewName, [
                        'action' => 'check-vies',
                        'color' => 'info',
                        'icon' => 'fa-solid fa-check-double',
                        'label' => 'check-vies'
                    ]);
                }
                break;
        }
    }

    /**
     * Loads language options for the widget
     * @param string $viewName
     */
    protected function loadLanguageOptions(string $viewName): void
    {
        $languageColumn = $this->views[$viewName]->columnForName('language');
        if ($languageColumn && $languageColumn->widget->getType() === 'select') {
            $languageOptions = [];
            foreach (SystemTools::language()->getAvailableLanguages() as $code => $name) {
                $languageOptions[] = ['value' => $code, 'title' => $name];
            }
            $languageColumn->widget->setValuesFromArray($languageOptions, false, true);
        }
    }

    /**
     * Updates related customer and supplier data
     * @param Contacto $contact
     */
    protected function updateRelations($contact): void
    {
        $customer = $contact->getCustomer(false);
        if ($customer->idcontactofact == $contact->idcontacto && $customer->exists()) {
            $customer->email = $contact->email;
            $customer->fax = $contact->fax;
            $customer->telefono1 = $contact->telefono1;
            $customer->telefono2 = $contact->telefono2;
            $customer->save();
        }

        $supplier = $contact->getSupplier(false);
        if ($supplier->idcontacto == $contact->idcontacto && $supplier->exists()) {
            $supplier->email = $contact->email;
            $supplier->fax = $contact->fax;
            $supplier->telefono1 = $contact->telefono1;
            $supplier->telefono2 = $contact->telefono2;
            $supplier->save();
        }
    }
}