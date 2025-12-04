<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2021 ERPIA Contributors
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

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Dinamic\Lib\ExtendedController\ListBusinessDocument;
use ERPIA\Dinamic\Model\PresupuestoCliente;

/**
 * Controller to list the items in the PresupuestoCliente model
 *
 * @author ERPIA Contributors
 */
class ListPresupuestoCliente extends ListBusinessDocument
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'sales';
        $pageData['title'] = 'estimations';
        $pageData['icon'] = 'fa-regular fa-file-powerpoint';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $this->createViewsPresupuestos();

        if ($this->permissions->onlyOwnerData === false) {
            $this->createViewLines('ListLineaPresupuestoCliente', 'LineaPresupuestoCliente');
        }
    }

    /**
     * Creates and configures the estimations view
     *
     * @param string $viewName
     */
    protected function createViewsPresupuestos(string $viewName = 'ListPresupuestoCliente')
    {
        $this->createViewSales($viewName, 'PresupuestoCliente', 'estimations');
        $this->addOrderBy($viewName, ['finoferta'], 'expiration');

        // Add document action buttons
        $this->addButtonGroupDocument($viewName);
        $this->addButtonApproveDocument($viewName);
    }

    /**
     * Execute actions before reading data
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        if (empty($action)) {
            $this->setExpiredItems();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Set expired status to estimations past their expiration date
     */
    protected function setExpiredItems()
    {
        $model = new PresupuestoCliente();

        // Select the available expired status
        $expiredStatus = null;
        foreach ($model->getAvailableStatus() as $status) {
            if (!$status->activo) {
                continue;
            }

            // Prefer status with idestado 23 if it meets conditions
            if ($status->idestado == 23 && !$status->editable && empty($status->generadoc)) {
                $expiredStatus = $status->idestado;
                break;
            } elseif (!$status->editable && empty($status->generadoc)) {
                $expiredStatus = $status->idestado;
            }
        }

        if ($expiredStatus === null) {
            return;
        }

        // Find editable estimations with expiration date not null
        $where = [
            new DataBaseWhere('editable', true),
            new DataBaseWhere('finoferta', null, 'IS NOT')
        ];

        $estimations = $model->getAll($where, ['finoferta' => 'ASC']);
        foreach ($estimations as $item) {
            if (time() < strtotime($item->finoferta)) {
                continue;
            }

            $item->idestado = $expiredStatus;
            $item->save();
        }
    }
}