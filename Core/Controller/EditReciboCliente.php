<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de recibos de clientes
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Currencies;
use ERPIA\Core\DataSrc\Companies;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\Helpers;
use ERPIA\Dinamic\Lib\Accounting\PaymentAccountingGenerator;
use ERPIA\Dinamic\Model\CustomerPayment;

/**
 * Controlador para la edición de recibos de clientes
 */
class EditReciboCliente extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'ReciboCliente';
    }

    /**
     * Obtiene los datos de configuración de la página
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageInfo = parent::getPageData();
        $pageInfo['menu'] = 'ventas';
        $pageInfo['title'] = 'recibo';
        $pageInfo['icon'] = 'fa-solid fa-piggy-bank';
        return $pageInfo;
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews(): void
    {
        parent::createViews();
        $this->configureTabPosition('bottom');

        $mainView = $this->getMainViewName();
        
        // Desactivar columnas cuando hay una única opción
        if (count(Companies::getAll()) <= 1) {
            $this->views[$mainView]->disableColumn('empresa');
        }
        
        if (count(Currencies::getAll()) <= 1) {
            $this->views[$mainView]->disableColumn('divisa');
        }

        // Desactivar botón de nuevo
        $this->configureViewOption($mainView, 'btnNew', false);

        $this->createPaymentsListView();
    }

    /**
     * Crea la vista de lista de pagos
     *
     * @param string $viewName
     */
    protected function createPaymentsListView(string $viewName = 'ListPagoCliente'): void
    {
        $this->addListView($viewName, 'PagoCliente', 'pagos');
        $this->views[$viewName]->addOrderBy(['fecha', 'hora'], 'fecha', 1);

        // Desactivar botón de nuevo
        $this->configureViewOption($viewName, 'btnNew', false);

        // Añadir botón para generar asiento contable
        $this->addCustomButton($viewName, [
            'action' => 'generate-accounting',
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'label' => 'generar-asiento-contable'
        ]);
    }

    /**
     * Ejecuta la acción de generación de asientos contables
     */
    protected function generateAccountingAction(): void
    {
        if ($this->userPermissions->allowUpdate === false) {
            Helpers::logWarning('no-permisos-modificacion');
            return;
        }
        
        if ($this->validateFormToken() === false) {
            return;
        }

        $selectedCodes = $this->request->getArray('codes');
        if (empty($selectedCodes) || is_array($selectedCodes) === false) {
            Helpers::logWarning('ningun-item-seleccionado');
            return;
        }

        foreach ($selectedCodes as $paymentCode) {
            $paymentRecord = new CustomerPayment();
            
            if ($paymentRecord->loadFromCode($paymentCode) === false) {
                Helpers::logWarning('registro-no-encontrado');
                continue;
            }
            
            if (!empty($paymentRecord->idasiento)) {
                Helpers::logWarning('asiento-ya-existente');
                continue;
            }

            $accountingTool = new PaymentAccountingGenerator();
            $accountingTool->createAccountingEntry($paymentRecord);
            
            if (empty($paymentRecord->idasiento) || $paymentRecord->save() === false) {
                Helpers::logError('error-guardar-registro');
                return;
            }
        }

        Helpers::logNotice('registros-actualizados-correctamente');
    }

    /**
     * Ejecuta acciones previas personalizadas
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction(string $action): bool
    {
        if ($action === 'generate-accounting') {
            $this->generateAccountingAction();
            return true;
        }

        return parent::execPreviousAction($action);
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
            case 'ListPagoCliente':
                $receiptId = $this->getViewModelValue('EditReciboCliente', 'idrecibo');
                $filterCondition = [new DataBaseWhere('idrecibo', $receiptId)];
                $this->views[$viewName]->loadData('', $filterCondition);
                break;

            case 'EditReciboCliente':
                parent::loadData($viewName, $view);
                $this->views[$viewName]->model->nick = $this->currentUser->nick;
                
                if ($this->views[$viewName]->model->pagado) {
                    $this->views[$viewName]->disableColumn('importe', false, 'true');
                    $this->views[$viewName]->disableColumn('gastos', false, 'true');
                    $this->views[$viewName]->disableColumn('forma-pago', false, 'true');
                }
                break;
        }
    }
}