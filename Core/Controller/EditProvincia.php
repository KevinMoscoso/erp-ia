<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de provincias
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
 * Controlador para la edición de registros de provincia
 */
class EditProvincia extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Provincia';
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
        $pageInfo['title'] = 'provincia';
        $pageInfo['icon'] = 'fa-solid fa-map-signs';
        return $pageInfo;
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews(): void
    {
        parent::createViews();
        $this->configureTabPosition('bottom');
        $this->createCityListView();
    }

    /**
     * Crea la vista de lista de ciudades
     *
     * @param string $viewName
     */
    protected function createCityListView(string $viewName = 'ListCiudad'): void
    {
        $this->addListView($viewName, 'Ciudad', 'ciudades', 'fa-solid fa-city')
            ->addOrderBy(['ciudad'], 'nombre')
            ->addOrderBy(['idprovincia'], 'provincia')
            ->addSearchFields(['ciudad', 'alias'])
            ->disableColumn('provincia');
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
            case 'ListCiudad':
                $provinceId = $this->getViewModelValue($this->getMainViewName(), 'idprovincia');
                $filterCondition = [new DataBaseWhere('idprovincia', $provinceId)];
                $view->loadData('', $filterCondition);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}