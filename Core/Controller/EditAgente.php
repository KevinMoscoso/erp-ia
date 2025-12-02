<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\ComercialContactController;
use ERPIA\Core\SystemTools;
use ERPIA\Dinamic\Model\Agente;
use ERPIA\Dinamic\Model\TotalModel;

/**
 * Controller to edit a single item from the Agente model
 *
 * @author ERPIA Team
 */
class EditAgente extends ComercialContactController
{
    /**
     * Calculates the agent's total outstanding invoices
     * @return string
     */
    public function calcAgentInvoicePending(): string
    {
        $agentCode = $this->getViewModelValue($this->getMainViewName(), 'codagente');
        $filters = [
            new DataBaseWhere('codagente', $agentCode),
            new DataBaseWhere('pagada', false)
        ];

        $totals = TotalModel::all('facturascli', $filters, ['total' => 'SUM(total)'], '')[0];
        return SystemTools::formatMoney($totals->totals['total']);
    }

    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Agente';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'admin';
        $pageConfig['title'] = 'agent';
        $pageConfig['icon'] = 'fa-solid fa-user-tie';
        return $pageConfig;
    }

    /**
     * Creates the contact view
     * @param string $viewName
     */
    protected function createContactView(string $viewName = 'EditContacto'): void
    {
        $this->addEditView($viewName, 'Contacto', 'contact', 'fa fa-address-book')
            ->disableColumn('agent')
            ->disableColumn('company')
            ->disableColumn('fiscal-id')
            ->disableColumn('fiscal-number')
            ->disableColumn('position')
            ->setSettings('btnDelete', false);
    }

    /**
     * Creates the customer list view
     * @param string $viewName
     */
    protected function createCustomerView(string $viewName = 'ListCliente'): void
    {
        $this->addListView($viewName, 'Cliente', 'customers', 'fa-solid fa-users')
            ->addSearchFields(['cifnif', 'codcliente', 'email', 'nombre', 'observaciones', 'razonsocial', 'telefono1', 'telefono2'])
            ->addOrderBy(['codcliente'], 'code')
            ->addOrderBy(['nombre'], 'name', 1)
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false);
    }

    /**
     * Creates a document view for the agent
     * @param string $viewName
     * @param string $model
     * @param string $label
     */
    protected function createDocumentView(string $viewName, string $model, string $label): void
    {
        $this->createCustomerListView($viewName, $model, $label);
        $this->tab($viewName)->setSettings('btnPrint', true);
        $this->addButtonGroupDocument($viewName);
        $this->addButtonApproveDocument($viewName);
    }

    /**
     * Creates the emails sent view
     * @param string $viewName
     */
    protected function createEmailsView(string $viewName = 'ListEmailSent'): void
    {
        $this->addListView($viewName, 'EmailSent', 'emails-sent', 'fa-solid fa-envelope')
            ->addSearchFields(['addressee', 'body', 'subject'])
            ->addOrderBy(['date'], 'date', 2)
            ->disableColumn('to')
            ->setSettings('btnNew', false);
    }

    /**
     * Creates an invoice view for the agent
     * @param string $viewName
     */
    protected function createInvoiceView(string $viewName): void
    {
        $this->createCustomerListView($viewName, 'FacturaCliente', 'invoices');
        $this->tab($viewName)->setSettings('btnPrint', true);
        $this->addButtonLockInvoice($viewName);
    }

    /**
     * Creates all views for the controller
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createContactView();
        $this->createCustomerView();
        $this->createEmailsView();

        if ($this->user->can('EditFacturaCliente')) {
            $this->createInvoiceView('ListFacturaCliente');
        }
        if ($this->user->can('EditAlbaranCliente')) {
            $this->createDocumentView('ListAlbaranCliente', 'AlbaranCliente', 'delivery-notes');
        }
        if ($this->user->can('EditPedidoCliente')) {
            $this->createDocumentView('ListPedidoCliente', 'PedidoCliente', 'orders');
        }
        if ($this->user->can('EditPresupuestoCliente')) {
            $this->createDocumentView('ListPresupuestoCliente', 'PresupuestoCliente', 'estimations');
        }
    }

    /**
     * Handles edit action with contact synchronization
     * @return bool
     */
    protected function editAction(): bool
    {
        $result = parent::editAction();
        if ($result && $this->active == 'EditContacto') {
            $agent = new Agente();
            $conditions = [new DataBaseWhere('idcontacto', $this->views[$this->active]->model->idcontacto)];
            if ($agent->load('', $conditions)) {
                $agent->email = $this->views[$this->active]->model->email;
                $agent->telefono1 = $this->views[$this->active]->model->telefono1;
                $agent->telefono2 = $this->views[$this->active]->model->telefono2;
                $agent->save();
            }
        }
        return $result;
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
            case 'EditContacto':
                $contactId = $this->getViewModelValue($mainView, 'idcontacto');
                if (empty($contactId)) {
                    $view->setSettings('active', false);
                    break;
                }
                $conditions = [new DataBaseWhere('idcontacto', $contactId)];
                $view->loadData('', $conditions);
                $this->loadLanguageValues($viewName);
                break;

            case 'ListAlbaranCliente':
            case 'ListCliente':
            case 'ListFacturaCliente':
            case 'ListPedidoCliente':
            case 'ListPresupuestoCliente':
                $agentCode = $this->getViewModelValue($mainView, 'codagente');
                $conditions = [new DataBaseWhere('codagente', $agentCode)];
                $view->loadData('', $conditions);
                break;

            case 'ListEmailSent':
                $agentEmail = $this->getViewModelValue($mainView, 'email');
                if (empty($agentEmail)) {
                    $view->setSettings('active', false);
                    break;
                }

                $conditions = [new DataBaseWhere('addressee', $agentEmail)];
                $view->loadData('', $conditions);

                $this->addButton($viewName, [
                    'action' => 'SendMail?email=' . $agentEmail,
                    'color' => 'success',
                    'icon' => 'fa-solid fa-envelope',
                    'label' => 'send',
                    'type' => 'link'
                ]);
                break;

            case $mainView:
                parent::loadData($viewName, $view);
                if (!$view->model->exists()) {
                    $view->disableColumn('contact');
                }
                break;
        }
    }

    /**
     * Loads available languages for language selection widget
     * @param string $viewName
     */
    protected function loadLanguageValues(string $viewName): void
    {
        $languageColumn = $this->views[$viewName]->columnForName('language');
        if ($languageColumn && $languageColumn->widget->getType() === 'select') {
            $languages = [];
            foreach (SystemTools::language()->getAvailableLanguages() as $code => $name) {
                $languages[] = ['value' => $code, 'title' => $name];
            }
            $languageColumn->widget->setValuesFromArray($languages, false, true);
        }
    }

    /**
     * Placeholder for custom widget values
     * @param string $viewName
     */
    protected function setCustomWidgetValues(string $viewName): void
    {
        // Custom widget configuration can be added here
    }
}