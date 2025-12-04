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

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Empresas;
use ERPIA\Core\Lib\ExtendedController\ListController;
use ERPIA\Core\Model\Ejercicio;
use ERPIA\Core\App\Translator;

/**
 * Controller to list the items in the Ejercicio model
 *
 * @author ERPIA Contributors
 */
class ListEjercicio extends ListController
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
        $pageData['title'] = 'exercises';
        $pageData['icon'] = 'fa-solid fa-calendar-alt';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $viewName = 'ListEjercicio';
        
        // Add the main view for exercises
        $this->addView($viewName, 'Ejercicio', 'exercises', 'fa-solid fa-calendar-alt');
        
        // Configure search fields
        $this->addSearchFields(['nombre', 'codejercicio']);
        
        // Configure default order
        $this->addOrderBy(['fechainicio'], 'start-date', 2);
        $this->addOrderBy(['codejercicio'], 'code');
        $this->addOrderBy(['nombre'], 'name');
        $this->addOrderBy(['idempresa, codejercicio'], 'company');

        // Company filter
        $this->addFilterSelect($viewName, 'idempresa', 'company', 'idempresa', Empresas::codeModel());

        // Status filter (all, only active, only closed)
        $this->addFilterSelectWhere($viewName, 'status', [
            [
                'label' => Translator::trans('all'),
                'where' => []
            ],
            [
                'label' => Translator::trans('only-active'),
                'where' => [new DataBaseWhere('estado', Ejercicio::EJERCICIO_ESTADO_ABIERTO)]
            ],
            [
                'label' => Translator::trans('only-closed'),
                'where' => [new DataBaseWhere('estado', Ejercicio::EJERCICIO_ESTADO_CERRADO)]
            ],
        ]);
    }
}