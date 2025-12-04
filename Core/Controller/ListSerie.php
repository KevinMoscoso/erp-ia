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
use ERPIA\Core\Translator;

/**
 * Controlador para listar los elementos del modelo Serie
 * 
 * Gestiona la visualización de series contables/fiscales
 * con filtros para tipo de serie y configuración de IVA.
 */
class ListSerie extends ListController
{
    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título e icono
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'accounting';
        $pageData['title'] = 'series';
        $pageData['icon'] = 'fa-solid fa-layer-group';
        
        return $pageData;
    }

    /**
     * Crea las vistas del controlador
     * 
     * Inicializa la vista de series contables
     */
    protected function createViews(): void
    {
        $this->createViewsSeries();
    }

    /**
     * Crea la vista principal de series
     * 
     * @param string $viewName Nombre de la vista (por defecto: ListSerie)
     */
    protected function createViewsSeries(string $viewName = 'ListSerie'): void
    {
        $this->addView($viewName, 'Serie', 'series', 'fa-solid fa-layer-group')
            ->addSearchFields(['descripcion', 'codserie'])
            ->addOrderBy(['codserie'], 'code')
            ->addOrderBy(['descripcion'], 'description');

        $translator = Translator::getInstance();

        // Filtro de series sin IVA
        $this->addFilterCheckbox($viewName, 'siniva', 'without-tax', 'siniva');

        // Filtro de tipo de serie
        $this->addFilterSelect($viewName, 'tipo', 'type', 'tipo', [
            '' => '------',
            'R' => $translator->trans('rectifying'),
            'S' => $translator->trans('simplified'),
        ]);
    }
}