<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2025 ERPIA Contributors
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
use ERPIA\Core\Lib\OperacionIVA;

/**
 * Controller to list the items in the Impuesto model
 *
 * @author ERPIA Contributors
 */
class ListImpuesto extends ListController
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'accounting';
        $pageData['title'] = 'taxes';
        $pageData['icon'] = 'fa-solid fa-plus-square';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $this->createViewsTax();
        $this->createViewsRetention();
    }

    /**
     * Creates and configures the tax view
     *
     * @param string $viewName
     */
    protected function createViewsTax(string $viewName = 'ListImpuesto'): void
    {
        // Add the main tax view
        $this->addView($viewName, 'Impuesto', 'taxes', 'fa-solid fa-plus-square');
        
        // Configure default order
        $this->addOrderBy(['codimpuesto'], 'code');
        $this->addOrderBy(['descripcion'], 'description');
        
        // Configure search fields
        $this->addSearchFields(['descripcion', 'codimpuesto']);
    }

    /**
     * Creates and configures the retention view
     *
     * @param string $viewName
     */
    protected function createViewsRetention(string $viewName = 'ListRetencion'): void
    {
        // Add the retention view
        $this->addView($viewName, 'Retencion', 'retentions', 'fa-solid fa-plus-square');
        
        // Configure default order
        $this->addOrderBy(['codretencion'], 'code');
        $this->addOrderBy(['descripcion'], 'description');
        
        // Configure search fields
        $this->addSearchFields(['descripcion', 'codretencion']);
    }

    /**
     * Load data into the view and set up operations for the main view
     *
     * @param string $viewName
     * @param object $view
     */
    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);
        
        // If this is the main view, load the operations for the operation column
        if ($viewName === $this->getMainViewName()) {
            $this->loadOperations($viewName);
        }
    }

    /**
     * Load the VAT operations into the operation column if it exists and is a select
     *
     * @param string $viewName
     */
    protected function loadOperations(string $viewName): void
    {
        // Get the column named 'operation' from the view
        $column = $this->views[$viewName]->columnForName('operation');
        
        // If the column exists and is a select widget, set its values from OperacionIVA
        if ($column && $column->widget->getType() === 'select') {
            $column->widget->setValuesFromArrayKeys(OperacionIVA::all(), true, true);
        }
    }
}