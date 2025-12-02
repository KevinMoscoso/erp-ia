<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * EditCiudad
 *
 * @author ERPIA Team
 */
class EditCiudad extends EditController
{
    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Ciudad';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'admin';
        $pageConfig['title'] = 'city';
        $pageConfig['icon'] = 'fa-solid fa-city';
        return $pageConfig;
    }

    /**
     * Creates all views for the controller
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
        $this->createPointsOfInterestView();
    }

    /**
     * Creates the points of interest view
     * @param string $viewName
     */
    protected function createPointsOfInterestView(string $viewName = 'ListPuntoInteresCiudad'): void
    {
        $this->addListView($viewName, 'PuntoInteresCiudad', 'points-of-interest', 'fa-solid fa-location-dot')
            ->addOrderBy(['name'], 'name')
            ->addOrderBy(['idciudad'], 'city')
            ->addSearchFields(['name', 'alias'])
            ->addFilterAutocomplete('idciudad', 'city', 'idciudad', 'ciudades', 'idciudad', 'ciudad')
            ->disableColumn('city');
    }

    /**
     * Loads data for each view
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListPuntoInteresCiudad':
                $cityId = $this->getViewModelValue($this->getMainViewName(), 'idciudad');
                $conditions = [new DataBaseWhere('idciudad', $cityId)];
                $view->loadData('', $conditions);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}