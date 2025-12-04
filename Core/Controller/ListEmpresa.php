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
 * Controller to list the items in the Empresa model
 *
 * @author ERPIA Contributors
 */
class ListEmpresa extends ListController
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'companies';
        $pageData['icon'] = 'fa-solid fa-building';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        // Add the main companies view
        $this->addView('ListEmpresa', 'Empresa', 'companies', 'fa-solid fa-building');
        
        // Configure search fields
        $this->addSearchFields(['nombre', 'nombrecorto']);
        
        // Configure default order
        $this->addOrderBy(['idempresa'], 'code');
        $this->addOrderBy(['nombre'], 'name');
    }
}