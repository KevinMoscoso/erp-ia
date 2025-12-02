<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controller to edit a single item from the Atributo model
 *
 * @author ERPIA Team
 */
class EditAtributo extends EditController
{
    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Atributo';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'warehouse';
        $pageConfig['title'] = 'attribute';
        $pageConfig['icon'] = 'fa-solid fa-tshirt';
        return $pageConfig;
    }

    /**
     * Creates all views for the controller
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
        $this->createAttributeValuesView();
    }

    /**
     * Creates the attribute values view
     * @param string $viewName
     */
    protected function createAttributeValuesView(string $viewName = 'EditAtributoValor'): void
    {
        $this->addEditListView($viewName, 'AtributoValor', 'attribute-values');
        $this->views[$viewName]->setInLine(true);
        $this->views[$viewName]->disableColumn('attribute');
    }

    /**
     * Loads data for each view
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditAtributoValor':
                $attributeCode = $this->getViewModelValue($this->getMainViewName(), 'codatributo');
                $conditions = [new DataBaseWhere('codatributo', $attributeCode)];
                $view->loadData('', $conditions, ['orden' => 'ASC', 'id' => 'DESC']);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}