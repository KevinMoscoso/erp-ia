<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2022 ERPIA Contributors
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

use ERPIA\Dinamic\Lib\ExtendedController\ListBusinessDocument;

/**
 * Controller to list the items in the PedidoProveedor model
 *
 * @author ERPIA Contributors
 */
class ListPedidoProveedor extends ListBusinessDocument
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'purchases';
        $pageData['title'] = 'orders';
        $pageData['icon'] = 'fa-solid fa-file-powerpoint';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        // Create the main orders view
        $this->createViewsPedidos();

        // Create lines view only if user has permission to see all data
        if ($this->permissions->onlyOwnerData === false) {
            $this->createViewLines('ListLineaPedidoProveedor', 'LineaPedidoProveedor');
        }
    }

    /**
     * Creates and configures the orders view for purchases
     *
     * @param string $viewName
     */
    protected function createViewsPedidos(string $viewName = 'ListPedidoProveedor')
    {
        // Create the purchases document view
        $this->createViewPurchases($viewName, 'PedidoProveedor', 'orders');
        
        // Add document action buttons
        $this->addButtonGroupDocument($viewName);
        $this->addButtonApproveDocument($viewName);
    }
}