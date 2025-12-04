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

use ERPIA\Core\Lib\ExtendedController\ListController;

/**
 * Controller to list the items in the AgenciaTransporte model
 *
 * @author ERPIA Contributors
 */
class ListAgenciaTransporte extends ListController
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
        $pageData['title'] = 'carriers';
        $pageData['icon'] = 'fa-solid fa-truck';
        return $pageData;
    }

    /**
     * Create and configure the view
     */
    protected function createViews()
    {
        // Create the main view for AgenciaTransporte
        $this->createAgenciaTransporteListView();
    }

    /**
     * Creates and configures the AgenciaTransporte list view
     */
    private function createAgenciaTransporteListView()
    {
        $viewId = 'ListAgenciaTransporte';
        $modelName = 'AgenciaTransporte';
        $viewTitle = 'carriers';
        $viewIcon = 'fa-solid fa-truck';
        
        // Add the main view
        $this->addView($viewId, $modelName, $viewTitle, $viewIcon);
        
        // Configure search fields
        $this->addSearchFields(['nombre', 'web', 'codtrans']);
        
        // Configure default order
        $this->addOrderBy(['codtrans'], 'code');
        $this->addOrderBy(['nombre'], 'name');
        
        // Add active filter
        $this->addFilterCheckbox('activo', 'active', 'activo');
    }
}