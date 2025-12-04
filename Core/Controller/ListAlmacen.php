<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2023 ERPIA Contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\DataSrc\Empresas;
use ERPIA\Core\Lib\ExtendedController\ListController;

/**
 * Controller to list the items in the Almacen model
 *
 * @author ERPIA Contributors
 */
class ListAlmacen extends ListController
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'warehouses';
        $pageData['icon'] = 'fa-solid fa-warehouse';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $this->createViewWarehouse();
    }

    /**
     * Creates and configures the warehouse view
     *
     * @param string $viewName
     */
    protected function createViewWarehouse(string $viewName = 'ListAlmacen'): void
    {
        // Add the main view for warehouses
        $this->addView($viewName, 'Almacen', 'warehouses', 'fa-solid fa-warehouse');
        
        // Configure search fields
        $this->addSearchFields([
            'apartado',
            'ciudad', 
            'codalmacen', 
            'codpostal', 
            'direccion', 
            'nombre', 
            'provincia'
        ]);
        
        // Configure default order
        $this->addOrderBy(['codalmacen'], 'code');
        $this->addOrderBy(['nombre'], 'name', 1);
        
        // Company filter
        $this->addFilterSelect($viewName, 'idempresa', 'company', 'idempresa', Empresas::codeModel());
    }
}