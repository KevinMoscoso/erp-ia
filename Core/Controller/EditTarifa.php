<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de tarifas
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
use ERPIA\Dinamic\Model\Customer;
use ERPIA\Dinamic\Model\CustomerGroup;

/**
 * Controlador para la edición de un registro del modelo Tarifa
 */
class EditTarifa extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Tarifa';
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
        $pageInfo['title'] = 'tarifa';
        $pageInfo['icon'] = 'fa-solid fa-percentage';
        return $pageInfo;
    }

    /**
     * Crea la vista de grupos de clientes
     *
     * @param string $viewName
     */
    protected function createCustomerGroupView(string $viewName = 'ListGrupoClientes'): void
    {
        $this->addListView($viewName, 'GrupoClientes', 'grupos-clientes', 'fa-solid fa-users-cog')
            ->addSearchFields(['nombre', 'codgrupo'])
            ->addOrderBy(['codgrupo'], 'codigo')
            ->addOrderBy(['nombre'], 'nombre', 1)
            ->disableColumn('tarifa')
            ->setOption('btnDelete', false)
            ->setOption('btnNew', false);

        // Añadir botones personalizados
        $this->addCustomButton($viewName, [
            'action' => 'setgrouprate',
            'color' => 'success',
            'icon' => 'fa-solid fa-folder-plus',
            'label' => 'agregar',
            'type' => 'modal'
        ]);
        $this->addCustomButton($viewName, [
            'action' => 'unsetgrouprate',
            'color' => 'danger',
            'confirm' => true,
            'icon' => 'fa-solid fa-folder-minus',
            'label' => 'quitar-de-lista'
        ]);
    }

    /**
     * Crea la vista de clientes
     *
     * @param string $viewName
     */
    protected function createCustomerView(string $viewName = 'ListCliente'): void
    {
        $this->addListView($viewName, 'Cliente', 'clientes', 'fa-solid fa-users')
            ->addSearchFields([
                'cifnif', 'codcliente', 'email', 'nombre', 
                'observaciones', 'razonsocial', 'telefono1', 'telefono2'
            ])
            ->addOrderBy(['codcliente'], 'codigo')
            ->addOrderBy(['nombre'], 'nombre', 1)
            ->addOrderBy(['fechaalta', 'codcliente'], 'fecha')
            ->setOption('btnDelete', false)
            ->setOption('btnNew', false);

        $this->addCustomButton($viewName, [
            'action' => 'setcustomerrate',
            'color' => 'success',
            'icon' => 'fa-solid fa-folder-plus',
            'label' => 'agregar',
            'type' => 'modal'
        ]);
        $this->addCustomButton($viewName, [
            'action' => 'unsetcustomerrate',
            'color' => 'danger',
            'confirm' => true,
            'icon' => 'fa-solid fa-folder-minus',
            'label' => 'quitar-de-lista'
        ]);
    }

    /**
     * Crea la vista de productos
     *
     * @param string $viewName
     */
    protected function createProductView(string $viewName = 'ListTarifaProducto'): void
    {
        $this->addListView($viewName, 'Join\TarifaProducto', 'productos', 'fa-solid fa-cubes')
            ->addOrderBy(['coste'], 'precio-costo')
            ->addOrderBy(['descripcion'], 'descripcion')
            ->addOrderBy(['precio'], 'precio')
            ->addOrderBy(['referencia'], 'referencia', 1)
            ->addSearchFields(['variantes.referencia', 'descripcion'])
            ->setOption('btnDelete', false)
            ->setOption('btnNew', false)
            ->setOption('checkBoxes', false);
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews(): void
    {
        parent::createViews();
        $this->configureTabPosition('bottom');

        $this->createProductView();
        $this->createCustomerGroupView();
        $this->createCustomerView();
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
            case 'ListCliente':
            case 'ListGrupoClientes':
            case 'ListTarifaProducto':
                $rateCode = $this->getViewModelValue($this->getMainViewName(), 'codtarifa');
                $filterCondition = [new DataBaseWhere('codtarifa', $rateCode)];
                $view->loadData('', $filterCondition);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
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
            case 'unsetcustomerrate':
                $this->removeCustomerRate();
                break;

            case 'unsetgrouprate':
                $this->removeGroupRate();
                break;

            case 'setcustomerrate':
                $this->assignCustomerRate();
                break;

            case 'setgrouprate':
                $this->assignGroupRate();
                break;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Desasigna la tarifa de los clientes seleccionados
     */
    protected function removeCustomerRate(): void
    {
        $selectedCodes = $this->request->getArray('codes');
        if (empty($selectedCodes) || is_array($selectedCodes) === false) {
            Helpers::logWarning('ningun-item-seleccionado');
            return;
        }

        $customerRecord = new Customer();
        foreach ($selectedCodes as $code) {
            if ($customerRecord->load($code)) {
                $customerRecord->codtarifa = null;
                $customerRecord->store();
            }
        }

        Helpers::logNotice('registros-actualizados-correctamente');
    }

    /**
     * Desasigna la tarifa de los grupos seleccionados
     */
    protected function removeGroupRate(): void
    {
        $selectedCodes = $this->request->getArray('codes');
        if (empty($selectedCodes) || is_array($selectedCodes) === false) {
            Helpers::logWarning('ningun-item-seleccionado');
            return;
        }

        $groupRecord = new CustomerGroup();
        foreach ($selectedCodes as $code) {
            if ($groupRecord->load($code)) {
                $groupRecord->codtarifa = null;
                $groupRecord->store();
            }
        }

        Helpers::logNotice('registros-actualizados-correctamente');
    }

    /**
     * Asigna la tarifa a un cliente específico
     */
    protected function assignCustomerRate(): void
    {
        $customerRecord = new Customer();
        $customerCode = $this->request->get('setcustomerrate');
        
        if (empty($customerCode) || $customerRecord->load($customerCode) === false) {
            Helpers::logWarning('cliente-no-encontrado');
            return;
        }

        $rateCode = $this->request->get('code');
        $customerRecord->codtarifa = $rateCode;
        
        if ($customerRecord->store()) {
            Helpers::logNotice('registro-actualizado-correctamente');
            return;
        }

        Helpers::logWarning('error-guardar-registro');
    }

    /**
     * Asigna la tarifa a un grupo específico
     */
    protected function assignGroupRate(): void
    {
        $groupRecord = new CustomerGroup();
        $groupCode = $this->request->get('setgrouprate');
        
        if (empty($groupCode) || $groupRecord->load($groupCode) === false) {
            Helpers::logWarning('grupo-no-encontrado');
            return;
        }

        $rateCode = $this->request->get('code');
        $groupRecord->codtarifa = $rateCode;
        
        if ($groupRecord->store()) {
            Helpers::logNotice('registro-actualizado-correctamente');
            return;
        }

        Helpers::logWarning('error-guardar-registro');
    }
}