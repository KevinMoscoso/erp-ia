<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de secuencias de documentos
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\DocumentNumberingManager;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\Helpers;

/**
 * Controlador para la edición de un registro del modelo SecuenciaDocumento
 */
class EditSecuenciaDocumento extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'SecuenciaDocumento';
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
        $pageInfo['title'] = 'secuencia-documento';
        $pageInfo['icon'] = 'fa-solid fa-code';
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

        // Desactivar columna de empresa si solo hay una
        if ($this->empresaModel->totalCount() < 2) {
            $this->views[$mainView]->disableColumn('empresa');
        }

        // Desactivar botones no necesarios
        $this->configureViewOption($mainView, 'btnOptions', false);
        $this->configureViewOption($mainView, 'btnPrint', false);

        // Crear vistas de documentos asociados
        $this->createDocumentListView('ListFacturaCliente', 'FacturaCliente', 'facturas-cliente');
        $this->createDocumentListView('ListFacturaProveedor', 'FacturaProveedor', 'facturas-proveedor');
        $this->createDocumentListView('ListAlbaranCliente', 'AlbaranCliente', 'albaranes-cliente');
        $this->createDocumentListView('ListAlbaranProveedor', 'AlbaranProveedor', 'albaranes-proveedor');
        $this->createDocumentListView('ListPedidoCliente', 'PedidoCliente', 'pedidos-cliente');
        $this->createDocumentListView('ListPedidoProveedor', 'PedidoProveedor', 'pedidos-proveedor');
        $this->createDocumentListView('ListPresupuestoCliente', 'PresupuestoCliente', 'presupuestos-cliente');
        $this->createDocumentListView('ListPresupuestoProveedor', 'PresupuestoProveedor', 'presupuestos-proveedor');
    }

    /**
     * Crea una vista de lista para documentos específicos
     *
     * @param string $viewName
     * @param string $modelName
     * @param string $title
     */
    protected function createDocumentListView(string $viewName, string $modelName, string $title): void
    {
        $this->addListView($viewName, $modelName, $title, 'fa-solid fa-copy')
            ->addOrderBy(['fecha', $this->convertColumnToNumber('numero')], 'fecha', 1)
            ->addOrderBy([$this->convertColumnToNumber('numero')], 'numero')
            ->addSearchFields(['cifnif', 'codigo', 'numero', 'observaciones']);

        $this->configureViewOption($viewName, 'btnNew', false);
        $this->configureViewOption($viewName, 'btnDelete', false);
    }

    /**
     * Carga datos en una vista específica
     *
     * @param string $viewName
     * @param \ERPIA\Core\Lib\ExtendedController\BaseView $view
     */
    protected function loadData(string $viewName, $view): void
    {
        $mainViewName = $this->getMainViewName();

        switch ($viewName) {
            case 'ListAlbaranCliente':
            case 'ListAlbaranProveedor':
            case 'ListFacturaCliente':
            case 'ListFacturaProveedor':
            case 'ListPedidoCliente':
            case 'ListPedidoProveedor':
            case 'ListPresupuestoCliente':
            case 'ListPresupuestoProveedor':
                $filters = [
                    new DataBaseWhere('codserie', $this->getViewModelValue($mainViewName, 'codserie')),
                    new DataBaseWhere('idempresa', $this->getViewModelValue($mainViewName, 'idempresa'))
                ];

                $currentSequence = $this->views[$mainViewName]->model;

                if (!empty($currentSequence->codejercicio)) {
                    $filters[] = new DataBaseWhere('codejercicio', $currentSequence->codejercicio);
                    $view->loadData('', $filters);
                    break;
                }

                $excludedExercises = DocumentNumberingManager::getAssignedExercises($currentSequence);
                if (!empty($excludedExercises)) {
                    $filters[] = new DataBaseWhere('codejercicio', implode(',', $excludedExercises), 'NOT IN');
                }

                $view->loadData('', $filters);
                break;

            case $mainViewName:
                parent::loadData($viewName, $view);

                // Desactivar todas las pestañas de documentos inicialmente
                $documentViews = [
                    'ListAlbaranCliente', 'ListAlbaranProveedor',
                    'ListFacturaCliente', 'ListFacturaProveedor',
                    'ListPedidoCliente', 'ListPedidoProveedor',
                    'ListPresupuestoCliente', 'ListPresupuestoProveedor'
                ];

                foreach ($documentViews as $docView) {
                    $this->configureViewOption($docView, 'active', false);
                }

                // Activar solo la pestaña del tipo de documento de la secuencia
                if (!empty($view->model->tipodoc)) {
                    $this->configureViewOption('List' . $view->model->tipodoc, 'active', true);
                }
                break;
        }
    }

    /**
     * Convierte una columna a tipo numérico según el motor de base de datos
     *
     * @param string $columnName
     * @return string
     */
    private function convertColumnToNumber(string $columnName): string
    {
        $databaseType = Helpers::config('db_type');
        
        if (strtolower($databaseType) === 'postgresql') {
            return 'CAST(' . $columnName . ' AS INTEGER)';
        }
        
        return 'CAST(' . $columnName . ' AS UNSIGNED)';
    }
}