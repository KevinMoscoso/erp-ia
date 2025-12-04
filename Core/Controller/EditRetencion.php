<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de retenciones
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controlador para la edición de un registro del modelo de retención
 */
class EditRetencion extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Retencion';
    }

    /**
     * Obtiene los datos de configuración de la página
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageInfo = parent::getPageData();
        $pageInfo['menu'] = 'contabilidad';
        $pageInfo['title'] = 'retencion';
        $pageInfo['icon'] = 'fa-solid fa-plus-square';
        return $pageInfo;
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews(): void
    {
        parent::createViews();
        $this->configureTabPosition('bottom');
        $this->createCustomersView();
        $this->createSuppliersView();
    }

    /**
     * Crea la vista de lista de clientes
     *
     * @param string $viewName
     */
    protected function createCustomersView(string $viewName = 'ListCliente'): void
    {
        $this->addListView($viewName, 'Cliente', 'clientes', 'fa-solid fa-users');
        $this->views[$viewName]->addSearchFields([
            'cifnif', 'codcliente', 'email', 'nombre', 
            'observaciones', 'razonsocial', 'telefono1', 'telefono2'
        ]);
        $this->views[$viewName]->addOrderBy(['nombre'], 'nombre', 1);

        // Desactivar botones
        $this->configureViewOption($viewName, 'btnNew', false);
        $this->configureViewOption($viewName, 'btnDelete', false);
    }

    /**
     * Crea la vista de lista de proveedores
     *
     * @param string $viewName
     */
    protected function createSuppliersView(string $viewName = 'ListProveedor'): void
    {
        $this->addListView($viewName, 'Proveedor', 'proveedores', 'fa-solid fa-users');
        $this->views[$viewName]->addSearchFields([
            'cifnif', 'codproveedor', 'email', 'nombre', 
            'observaciones', 'razonsocial', 'telefono1', 'telefono2'
        ]);
        $this->views[$viewName]->addOrderBy(['nombre'], 'nombre', 1);

        // Desactivar botones
        $this->configureViewOption($viewName, 'btnNew', false);
        $this->configureViewOption($viewName, 'btnDelete', false);
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
            case 'ListProveedor':
                $retencionCode = $this->getViewModelValue($this->getMainViewName(), 'codretencion');
                $filterCondition = [new DataBaseWhere('codretencion', $retencionCode)];
                $view->loadData('', $filterCondition);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}