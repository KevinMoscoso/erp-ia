<?php
/**
 * ERPIA - Sistema de Gestión Empresarial
 * Este archivo es parte de ERPIA, software libre bajo licencia GPL.
 * 
 * @package    ERPIA\Core\Controller
 * @author     Equipo de Desarrollo ERPIA
 * @copyright  2023-2025 ERPIA
 * @license    GNU Lesser General Public License v3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Dinamic\Lib\ExtendedController\ListBusinessDocument;

/**
 * Controlador para listar los elementos del modelo PresupuestoProveedor
 * 
 * Gestiona la visualización de presupuestos de proveedor (estimaciones de compra)
 * con vistas de listado y líneas relacionadas según permisos de usuario.
 */
class ListPresupuestoProveedor extends ListBusinessDocument
{
    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título e icono
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'purchases';
        $pageData['title'] = 'estimations';
        $pageData['icon'] = 'fa-regular fa-file-powerpoint';
        
        return $pageData;
    }

    /**
     * Crea las vistas del controlador
     * 
     * Inicializa la vista principal de presupuestos y,
     * si los permisos lo permiten, la vista de líneas.
     */
    protected function createViews(): void
    {
        $this->createViewsPresupuestos();
        
        if ($this->permissions->onlyOwnerData === false) {
            $this->createViewLines('ListLineaPresupuestoProveedor', 'LineaPresupuestoProveedor');
        }
    }

    /**
     * Crea la vista principal de presupuestos de proveedor
     * 
     * @param string $viewName Nombre de la vista (por defecto: ListPresupuestoProveedor)
     */
    protected function createViewsPresupuestos(string $viewName = 'ListPresupuestoProveedor'): void
    {
        $this->createViewPurchases($viewName, 'PresupuestoProveedor', 'estimations');
        
        $this->addButtonGroupDocument($viewName);
        $this->addButtonApproveDocument($viewName);
    }
}