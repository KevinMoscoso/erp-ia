<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de proveedores
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\ComercialContactController;
use ERPIA\Core\Helpers;
use ERPIA\Dinamic\Lib\TaxRegime;
use ERPIA\Dinamic\Lib\VendorRiskManager;

/**
 * Controlador para edición de proveedores en el sistema ERPIA
 */
class EditProveedor extends ComercialContactController
{
    /**
     * Obtiene el riesgo total de albaranes pendientes del proveedor
     *
     * @return string
     */
    public function getDeliveryNotesRisk(): string
    {
        $supplierCode = $this->getViewModelValue('EditProveedor', 'codproveedor');
        $totalRisk = VendorRiskManager::calculateDeliveryNotesRisk($supplierCode);
        return Helpers::formatCurrency($totalRisk);
    }

    /**
     * Obtiene la URL de la imagen del proveedor
     *
     * @return string
     */
    public function getImageUrl(): string
    {
        $mainView = $this->getMainViewName();
        return $this->views[$mainView]->model->getGravatarUrl();
    }

    /**
     * Obtiene el riesgo total de facturas pendientes del proveedor
     *
     * @return string
     */
    public function getInvoicesRisk(): string
    {
        $supplierCode = $this->getViewModelValue('EditProveedor', 'codproveedor');
        $totalRisk = VendorRiskManager::calculateInvoicesRisk($supplierCode);
        return Helpers::formatCurrency($totalRisk);
    }

    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Proveedor';
    }

    /**
     * Obtiene los datos de configuración de la página
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageInfo = parent::getPageData();
        $pageInfo['menu'] = 'compras';
        $pageInfo['title'] = 'proveedor';
        $pageInfo['icon'] = 'fa-solid fa-users';
        return $pageInfo;
    }

    /**
     * Crea una vista de documentos de proveedor
     *
     * @param string $viewName
     * @param string $modelName
     * @param string $label
     */
    protected function createDocumentView(string $viewName, string $modelName, string $label): void
    {
        $this->createSupplierListView($viewName, $modelName, $label);

        $this->configureViewOption($viewName, 'btnPrint', true);
        $this->addDocumentActionButtons($viewName);
        $this->addDocumentApprovalButton($viewName);
    }

    /**
     * Crea vista de facturas de proveedor
     *
     * @param string $viewName
     */
    protected function createInvoiceView(string $viewName): void
    {
        $this->createSupplierListView($viewName, 'FacturaProveedor', 'facturas');

        $this->configureViewOption($viewName, 'btnPrint', true);
        $this->addInvoiceLockButton($viewName);
    }

    /**
     * Crea vista de productos del proveedor
     *
     * @param string $viewName
     */
    protected function createProductView(string $viewName = 'ListProductoProveedor'): void
    {
        $this->addListView($viewName, 'ProductoProveedor', 'productos', 'fa-solid fa-cubes')
            ->addOrderBy(['actualizado'], 'fecha-actualizacion', 2)
            ->addOrderBy(['referencia'], 'referencia')
            ->addOrderBy(['refproveedor'], 'referencia-proveedor')
            ->addOrderBy(['neto'], 'importe-neto')
            ->addOrderBy(['stock'], 'existencia')
            ->addSearchFields(['referencia', 'refproveedor'])
            ->disableColumn('proveedor');

        $this->configureViewOption($viewName, 'btnNew', false);
        $this->configureViewOption($viewName, 'btnPrint', true);
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews(): void
    {
        parent::createViews();

        $this->createContactsView();
        $this->addEditListView('EditCuentaBancoProveedor', 'CuentaBancoProveedor', 'cuentas-bancarias', 'fa-solid fa-piggy-bank');

        if ($this->currentUser->hasPermission('EditSubcuenta')) {
            $this->createSubaccountsView();
        }

        $this->createEmailsView();
        $this->createDocumentFilesView();

        if ($this->currentUser->hasPermission('EditProducto')) {
            $this->createProductView();
        }
        
        if ($this->currentUser->hasPermission('EditFacturaProveedor')) {
            $this->createInvoiceView('ListFacturaProveedor');
        }
        
        if ($this->currentUser->hasPermission('EditAlbaranProveedor')) {
            $this->createDocumentView('ListAlbaranProveedor', 'AlbaranProveedor', 'albaranes');
        }
        
        if ($this->currentUser->hasPermission('EditPedidoProveedor')) {
            $this->createDocumentView('ListPedidoProveedor', 'PedidoProveedor', 'pedidos');
        }
        
        if ($this->currentUser->hasPermission('EditPresupuestoProveedor')) {
            $this->createDocumentView('ListPresupuestoProveedor', 'PresupuestoProveedor', 'presupuestos');
        }
        
        if ($this->currentUser->hasPermission('EditReciboProveedor')) {
            $this->createReceiptView('ListReciboProveedor', 'ReciboProveedor');
        }
    }

    /**
     * Ejecuta acción de edición
     *
     * @return bool
     */
    protected function editAction(): bool
    {
        $result = parent::editAction();
        
        if ($result && $this->activeView === $this->getMainViewName()) {
            $currentModel = $this->views[$this->activeView]->model;
            $this->validateSubaccountLength($currentModel->codsubcuenta);
            $this->syncContactInformation($currentModel);
        }
        
        return $result;
    }

    /**
     * Ejecuta acción de inserción
     *
     * @return bool
     */
    protected function insertAction(): bool
    {
        if (parent::insertAction() === false) {
            return false;
        }

        $returnUrl = $this->request->get('return');
        if (empty($returnUrl)) {
            return true;
        }

        $currentModel = $this->views[$this->activeView]->model;
        $primaryKey = $currentModel->getPrimaryKeyColumn();
        $recordId = $currentModel->getId();

        if (strpos($returnUrl, '?') === false) {
            $redirectUrl = $returnUrl . '?' . $primaryKey . '=' . $recordId;
        } else {
            $redirectUrl = $returnUrl . '&' . $primaryKey . '=' . $recordId;
        }

        $this->redirect($redirectUrl);
        return true;
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
        $supplierCode = $this->getViewModelValue($mainView, 'codproveedor');
        $filterCondition = [new DataBaseWhere('codproveedor', $supplierCode)];

        switch ($viewName) {
            case 'EditCuentaBancoProveedor':
                $view->loadData('', $filterCondition, ['codcuenta' => 'DESC']);
                break;

            case 'EditDireccionContacto':
                $view->loadData('', $filterCondition, ['idcontacto' => 'DESC']);
                break;

            case 'ListFacturaProveedor':
                $view->loadData('', $filterCondition);
                $this->addAccountingGenerationButton($viewName, $supplierCode);
                break;

            case 'ListAlbaranProveedor':
            case 'ListPedidoProveedor':
            case 'ListPresupuestoProveedor':
            case 'ListProductoProveedor':
            case 'ListReciboProveedor':
                $view->loadData('', $filterCondition);
                break;

            case 'ListLineaFacturaProveedor':
                $subQuery = 'SELECT idfactura FROM facturasprov WHERE codproveedor = ' . 
                           $this->database->escapeString($supplierCode);
                $invoiceFilter = [new DataBaseWhere('idfactura', $subQuery, 'IN')];
                $view->loadData('', $invoiceFilter);
                break;

            case $mainView:
                parent::loadData($viewName, $view);
                $this->loadLanguageOptions($viewName);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Carga opciones de idioma disponibles
     *
     * @param string $viewName
     */
    protected function loadLanguageOptions(string $viewName): void
    {
        $languageColumn = $this->views[$viewName]->getColumn('language');
        
        if ($languageColumn && $languageColumn->widget->getType() === 'select') {
            $availableLanguages = [];
            $languageManager = Helpers::getLanguageManager();
            
            foreach ($languageManager->getAvailableLanguages() as $code => $name) {
                $availableLanguages[] = ['value' => $code, 'title' => $name];
            }
            
            $languageColumn->widget->setOptions($availableLanguages, false, true);
        }
    }

    /**
     * Configura valores personalizados para widgets
     *
     * @param string $viewName
     */
    protected function setCustomWidgetValues(string $viewName): void
    {
        $vatColumn = $this->views[$viewName]->getColumn('vat-regime');
        if ($vatColumn && $vatColumn->widget->getType() === 'select') {
            $vatColumn->widget->setOptionsFromArray(TaxRegime::getAll(), true);
        }

        if ($this->views[$viewName]->model->exists() === false) {
            $this->views[$viewName]->disableColumn('contacto');
            return;
        }

        $supplierCode = $this->getViewModelValue($viewName, 'codproveedor');
        $contactFilter = [new DataBaseWhere('codproveedor', $supplierCode)];
        $contactList = $this->codeModel->getAll('contactos', 'idcontacto', 'descripcion', false, $contactFilter);

        $contactColumn = $this->views[$viewName]->getColumn('contact');
        if ($contactColumn && $contactColumn->widget->getType() === 'select') {
            $contactColumn->widget->setOptionsFromCodeModel($contactList);
        }
    }
}