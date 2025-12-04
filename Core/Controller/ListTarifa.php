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

use ERPIA\Core\Lib\ExtendedController\ListController;

/**
 * Controlador para listar los elementos del modelo Tarifa
 * 
 * Gestiona la visualización de tarifas comerciales
 * con opciones de búsqueda y ordenación básicas.
 */
class ListTarifa extends ListController
{
    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título e icono
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'sales';
        $pageData['title'] = 'rates';
        $pageData['icon'] = 'fa-solid fa-percentage';
        
        return $pageData;
    }

    /**
     * Crea las vistas del controlador
     * 
     * Inicializa la vista de tarifas comerciales
     */
    protected function createViews(): void
    {
        $viewName = 'ListTarifa';
        $this->addView($viewName, 'Tarifa', 'rates', 'fa-solid fa-percentage')
            ->addSearchFields(['nombre', 'codtarifa'])
            ->addOrderBy(['codtarifa'], 'code')
            ->addOrderBy(['nombre'], 'name', 1);
    }
}