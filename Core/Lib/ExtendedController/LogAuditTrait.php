<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2023-2025 ERPIA Team
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

namespace ERPIA\Lib\ExtendedController;

use ERPIA\Core\Base\DataBase\DataBaseWhere;

/**
 * Trait for audit log functionality in extended controllers
 *
 * @author ERPIA Team
 */
trait LogAuditTrait
{
    /**
     * Creates a view for audit log messages
     *
     * @param string $viewName
     */
    public function createAuditLogView(string $viewName = 'ListLogMessage')
    {
        $this->addView($viewName, 'LogMessage', 'history', 'fa-solid fa-history');
        $this->views[$viewName]->addOrderBy(['timestamp'], 'date', 2);
        $this->views[$viewName]->addSearchFields(['context', 'message']);

        // disable specific columns
        $this->views[$viewName]->disableColumn('log_channel');
        $this->views[$viewName]->disableColumn('source_url');

        // disable buttons and checkboxes
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'checkBoxes', false);
    }

    /**
     * Loads audit log data for a specific model
     *
     * @param mixed $view
     * @param string $modelName
     * @param string $modelId
     */
    public function loadAuditLogData($view, $modelName, $modelId)
    {
        $where = [
            new DataBaseWhere('model_name', $modelName),
            new DataBaseWhere('model_identifier', $modelId)
        ];
        $view->loadData('', $where);
    }
}