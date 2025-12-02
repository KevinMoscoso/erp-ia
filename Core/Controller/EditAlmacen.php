<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\SystemTools;

/**
 * Controller to edit a single item from the Almacen model
 *
 * @author ERPIA Team
 */
class EditAlmacen extends EditController
{
    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Almacen';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'warehouse';
        $pageConfig['title'] = 'warehouse';
        $pageConfig['icon'] = 'fa-solid fa-warehouse';
        return $pageConfig;
    }

    /**
     * Creates the stock view for the warehouse
     * @param string $viewName
     */
    protected function createStockView(string $viewName = 'ListStock'): void
    {
        $this->addListView($viewName, 'Join\StockProducto', 'stock', 'fa-solid fa-dolly')
            ->addSearchFields(['stocks.referencia', 'stocks.ubicacion', 'productos.descripcion'])
            ->addOrderBy(['stocks.referencia'], 'reference')
            ->addOrderBy(['stocks.cantidad'], 'quantity')
            ->addOrderBy(['stocks.disponible'], 'available')
            ->addOrderBy(['stocks.reservada'], 'reserved')
            ->addOrderBy(['stocks.pterecibir'], 'pending-reception')
            ->addOrderBy(['productos.descripcion', 'stocks.referencia'], 'product');

        // Manufacturer filter
        $manufacturers = $this->codeModel->all('fabricantes', 'codfabricante', 'nombre');
        $this->listView($viewName)->addFilterSelect('manufacturer', 'manufacturer', 'productos.codfabricante', $manufacturers);

        // Family filter
        $families = $this->codeModel->all('familias', 'codfamilia', 'descripcion');
        $this->listView($viewName)->addFilterSelect('family', 'family', 'productos.codfamilia', $families);

        // Stock type filter
        $this->listView($viewName)->addFilterSelectWhere('type', [
            [
                'label' => SystemTools::translate('all'),
                'where' => []
            ],
            [
                'label' => '------',
                'where' => []
            ],
            [
                'label' => SystemTools::translate('under-minimums'),
                'where' => [new DataBaseWhere('stocks.disponible', 'field:stockmin', '<')]
            ],
            [
                'label' => SystemTools::translate('excess'),
                'where' => [new DataBaseWhere('stocks.disponible', 'field:stockmax', '>')]
            ]
        ]);

        // Quantity filters
        $this->listView($viewName)
            ->addFilterNumber('max-stock', 'quantity', 'cantidad', '>=')
            ->addFilterNumber('min-stock', 'quantity', 'cantidad', '<=');

        // Disable warehouse column and buttons
        $this->tab($viewName)->disableColumn('warehouse');
        $this->tab($viewName)
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false);
    }

    /**
     * Creates all views for the controller
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        // Disable company column if only one company exists
        if ($this->empresa->count() < 2) {
            $this->views[$this->getMainViewName()]->disableColumn('company');
        }

        $this->createStockView();
    }

    /**
     * Loads data for each view
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListStock':
                $warehouseCode = $this->getViewModelValue($this->getMainViewName(), 'codalmacen');
                $conditions = [new DataBaseWhere('stocks.codalmacen', $warehouseCode)];
                $view->loadData('', $conditions);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}