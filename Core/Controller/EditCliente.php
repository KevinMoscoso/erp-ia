<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\ComercialContactController;
use ERPIA\Core\SystemTools;
use ERPIA\Dinamic\Lib\CustomerRiskTools;
use ERPIA\Dinamic\Lib\RegimenIVA;

/**
 * Controller to edit a single item from the Cliente model
 *
 * @author ERPIA Team
 */
class EditCliente extends ComercialContactController
{
    /**
     * Calculates the customer's risk on pending delivery notes
     * @return string
     */
    public function getDeliveryNotesRisk(): string
    {
        $customerCode = $this->getViewModelValue('EditCliente', 'codcliente');
        $totalAmount = empty($customerCode) ? 0 : CustomerRiskTools::getDeliveryNotesRisk($customerCode);
        return SystemTools::formatMoney($totalAmount);
    }

    /**
     * Returns the customer's Gravatar image URL
     * @return string
     */
    public function getImageUrl(): string
    {
        $mainView = $this->getMainViewName();
        return $this->views[$mainView]->model->gravatar();
    }

    /**
     * Calculates the customer's risk on unpaid invoices
     * @return string
     */
    public function getInvoicesRisk(): string
    {
        $customerCode = $this->getViewModelValue('EditCliente', 'codcliente');
        $totalAmount = empty($customerCode) ? 0 : CustomerRiskTools::getInvoicesRisk($customerCode);
        return SystemTools::formatMoney($totalAmount);
    }

    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Cliente';
    }

    /**
     * Calculates the customer's risk on pending orders
     * @return string
     */
    public function getOrdersRisk(): string
    {
        $customerCode = $this->getViewModelValue('EditCliente', 'codcliente');
        $totalAmount = empty($customerCode) ? 0 : CustomerRiskTools::getOrdersRisk($customerCode);
        return SystemTools::formatMoney($totalAmount);
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'sales';
        $pageConfig['title'] = 'customer';
        $pageConfig['icon'] = 'fa-solid fa-users';
        return $pageConfig;
    }

    /**
     * Creates a document view for the customer
     * @param string $viewName
     * @param string $model
     * @param string $label
     */
    protected function createDocumentView(string $viewName, string $model, string $label): void
    {
        $this->createCustomerListView($viewName, $model, $label);
        $this->setSettings($viewName, 'btnPrint', true);
        $this->addButtonGroupDocument($viewName);
        $this->addButtonApproveDocument($viewName);
    }

    /**
     * Creates an invoice view for the customer
     * @param string $viewName
     */
    protected function createInvoiceView(string $viewName): void
    {
        $this->createCustomerListView($viewName, 'FacturaCliente', 'invoices');
        $this->setSettings($viewName, 'btnPrint', true);
        $this->addButtonLockInvoice($viewName);
    }

    /**
     * Creates all views for the controller
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createContactsView();
        $this->addEditListView('EditCuentaBancoCliente', 'CuentaBancoCliente', 'customer-banking-accounts', 'fa-solid fa-piggy-bank');

        if ($this->user->can('EditSubcuenta')) {
            $this->createSubaccountsView();
        }

        $this->createEmailsView();
        $this->createViewDocFiles();

        if ($this->user->can('EditFacturaCliente')) {
            $this->createInvoiceView('ListFacturaCliente');
            $this->createLineView('ListLineaFacturaCliente', 'LineaFacturaCliente');
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
        if ($this->user->can('EditReciboCliente')) {
            $this->createReceiptView('ListReciboCliente', 'ReciboCliente');
        }
    }

    /**
     * Handles edit action with additional validations
     * @return bool
     */
    protected function editAction(): bool
    {
        $result = parent::editAction();
        if ($result && $this->active === $this->getMainViewName()) {
            $this->checkSubaccountLength($this->getModel()->codsubcuenta);
            $this->updateContact($this->views[$this->active]->model);
        }
        return $result;
    }

    /**
     * Handles insert action with return URL redirection
     * @return bool
     */
    protected function insertAction(): bool
    {
        if (!parent::insertAction()) {
            return false;
        }

        $returnUrl = $this->request->query('return');
        if (empty($returnUrl)) {
            return true;
        }

        $model = $this->views[$this->active]->model;
        $separator = strpos($returnUrl, '?') === false ? '?' : '&';
        $redirectUrl = $returnUrl . $separator . $model->primaryColumn() . '=' . $model->id();
        $this->redirect($redirectUrl);
        return true;
    }

    /**
     * Loads data for each view
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainViewName = $this->getMainViewName();
        $customerCode = $this->getViewModelValue($mainViewName, 'codcliente');
        $conditions = [new DataBaseWhere('codcliente', $customerCode)];

        switch ($viewName) {
            case 'EditCuentaBancoCliente':
                $view->loadData('', $conditions, ['codcuenta' => 'DESC']);
                break;

            case 'EditDireccionContacto':
                $view->loadData('', $conditions, ['idcontacto' => 'DESC']);
                break;

            case 'ListFacturaCliente':
                $view->loadData('', $conditions);
                $this->addButtonGenerateAccountingInvoices($viewName, $customerCode);
                break;

            case 'ListAlbaranCliente':
            case 'ListPedidoCliente':
            case 'ListPresupuestoCliente':
            case 'ListReciboCliente':
                $view->loadData('', $conditions);
                break;

            case 'ListLineaFacturaCliente':
                $subquery = 'SELECT idfactura FROM facturascli WHERE codcliente = ' . $this->dataBase->var2str($customerCode);
                $where = [new DataBaseWhere('idfactura', $subquery, 'IN')];
                $view->loadData('', $where);
                break;

            case $mainViewName:
                parent::loadData($viewName, $view);
                $this->loadLanguageOptions($viewName);
                $this->loadVatExceptionOptions($viewName);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Loads VAT exception options for the widget
     * @param string $viewName
     */
    protected function loadVatExceptionOptions(string $viewName): void
    {
        $vatExceptionColumn = $this->views[$viewName]->columnForName('vat-exception');
        if ($vatExceptionColumn && $vatExceptionColumn->widget->getType() === 'select') {
            $vatExceptionColumn->widget->setValuesFromArrayKeys(RegimenIVA::allExceptions(), true, true);
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
     * Sets custom widget values
     * @param string $viewName
     */
    protected function setCustomWidgetValues(string $viewName): void
    {
        // Configure VAT regime select widget
        $vatRegimeColumn = $this->views[$viewName]->columnForName('vat-regime');
        if ($vatRegimeColumn && $vatRegimeColumn->widget->getType() === 'select') {
            $vatRegimeColumn->widget->setValuesFromArrayKeys(RegimenIVA::all(), true);
        }

        // Disable address columns for new records
        if (!$this->views[$viewName]->model->exists()) {
            $this->views[$viewName]->disableColumn('billing-address');
            $this->views[$viewName]->disableColumn('shipping-address');
            return;
        }

        $customerCode = $this->getViewModelValue($viewName, 'codcliente');
        $contactConditions = [new DataBaseWhere('codcliente', $customerCode)];
        $contacts = $this->codeModel->all('contactos', 'idcontacto', 'descripcion', false, $contactConditions);

        // Configure billing address select widget
        $billingAddressColumn = $this->views[$viewName]->columnForName('billing-address');
        if ($billingAddressColumn && $billingAddressColumn->widget->getType() === 'select') {
            $billingAddressColumn->widget->setValuesFromCodeModel($contacts);
        }

        // Configure shipping address select widget
        $shippingAddressColumn = $this->views[$viewName]->columnForName('shipping-address');
        if ($shippingAddressColumn && $shippingAddressColumn->widget->getType() === 'select') {
            $shippingContacts = $this->codeModel->all('contactos', 'idcontacto', 'descripcion', true, $contactConditions);
            $shippingAddressColumn->widget->setValuesFromCodeModel($shippingContacts);
        }
    }
}