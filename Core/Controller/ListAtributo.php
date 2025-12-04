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
 * Controller to list the items in the Atributo model
 *
 * @author ERPIA Contributors
 */
class ListAtributo extends ListController
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
        $pageData['title'] = 'attributes';
        $pageData['icon'] = 'fa-solid fa-tshirt';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $this->createViewsAttributes();
        $this->createViewsValues();
    }

    /**
     * Creates and configures the attributes view
     *
     * @param string $viewName
     */
    protected function createViewsAttributes(string $viewName = 'ListAtributo'): void
    {
        // Add the main view for attributes
        $this->addView($viewName, 'Atributo', 'attributes', 'fa-solid fa-tshirt');
        
        // Configure search fields
        $this->addSearchFields(['nombre', 'codatributo']);
        
        // Configure default order
        $this->addOrderBy(['codatributo'], 'code');
        $this->addOrderBy(['nombre'], 'name');
    }

    /**
     * Creates and configures the attribute values view
     *
     * @param string $viewName
     */
    protected function createViewsValues(string $viewName = 'ListAtributoValor'): void
    {
        // Add the view for attribute values
        $this->addView($viewName, 'AtributoValor', 'values', 'fa-solid fa-list');
        
        // Configure search fields
        $this->addSearchFields(['valor', 'codatributo']);
        
        // Configure default order
        $this->addOrderBy(['codatributo', 'orden', 'valor'], 'sort', 2);
        $this->addOrderBy(['codatributo', 'valor'], 'value');
        
        // Attribute filter
        $attributes = $this->codeModel->getAll('atributos', 'codatributo', 'nombre');
        $this->addFilterSelect($viewName, 'codatributo', 'attribute', 'codatributo', $attributes);
    }
}