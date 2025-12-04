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
 * Controller to list the items in the Familia model
 *
 * @author ERPIA Contributors
 */
class ListFamilia extends ListController
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
        $pageData['title'] = 'families';
        $pageData['icon'] = 'fa-solid fa-sitemap';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $viewName = 'ListFamilia';
        
        // Add the main families view
        $this->addView($viewName, 'Familia', 'families', 'fa-solid fa-sitemap');
        
        // Configure search fields
        $this->addSearchFields(['descripcion', 'codfamilia', 'madre']);
        
        // Configure default order
        $this->addOrderBy(['codfamilia'], 'code');
        $this->addOrderBy(['descripcion'], 'description');
        $this->addOrderBy(['madre'], 'parent');
        $this->addOrderBy(['numproductos'], 'products');

        // Parent family filter
        $parentValues = $this->codeModel->getAll('familias', 'codfamilia', 'descripcion');
        $this->addFilterSelect($viewName, 'madre', 'parent', 'madre', $parentValues);
    }
}