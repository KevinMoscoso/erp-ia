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
use ERPIA\Core\Model\Asiento;
use ERPIA\Core\App\Translator;
use ERPIA\Core\App\Configuration;

/**
 * Controller to list the items in the Asiento model
 *
 * @author ERPIA Contributors
 */
class ListAsiento extends ListController
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
        $pageData['title'] = 'accounting-entries';
        $pageData['icon'] = 'fa-solid fa-balance-scale';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $this->createViewsAccountEntries();
        $this->createViewsNotBalanced();
        $this->createViewsConcepts();
        $this->createViewsJournals();
    }

    /**
     * Creates and configures the main accounting entries view
     *
     * @param string $viewName
     */
    protected function createViewsAccountEntries(string $viewName = 'ListAsiento'): void
    {
        $this->addView($viewName, 'Asiento', 'accounting-entries', 'fa-solid fa-balance-scale');
        
        // Configure search fields
        $this->addSearchFields(['concepto', 'documento', 'CAST(numero AS char(255))']);
        
        // Configure default order
        $this->addOrderBy(['fecha', 'numero'], 'date', 2);
        $this->addOrderBy(['numero', 'idasiento'], 'number');
        $this->addOrderBy(['importe', 'idasiento'], 'amount');

        // Filters
        $this->addFilterPeriod($viewName, 'date', 'period', 'fecha');
        $this->addFilterNumber('min-total', 'amount', 'importe', '>=');
        $this->addFilterNumber('max-total', 'amount', 'importe', '<=');
        $this->addFilterCheckbox('editable');

        // Operation filter
        $operations = [
            '' => '------',
            Asiento::OPERATION_OPENING => Translator::trans('opening-operation'),
            Asiento::OPERATION_CLOSING => Translator::trans('closing-operation'),
            Asiento::OPERATION_REGULARIZATION => Translator::trans('regularization-operation')
        ];
        $this->addFilterSelect($viewName, 'operacion', 'operation', 'operacion', $operations);

        // Company filter
        $selectCompany = Empresas::codeModel();
        if (count($selectCompany) > 2) {
            $this->addFilterSelect($viewName, 'idempresa', 'company', 'idempresa', $selectCompany);
        }

        // Exercise filter
        $selectExercise = $this->getSelectExercise();
        if (count($selectExercise) > 2) {
            $this->addFilterSelect($viewName, 'codejercicio', 'exercise', 'codejercicio', $selectExercise);
        }

        // Journal filter
        $selectJournals = $this->codeModel->getAll('diarios', 'iddiario', 'descripcion');
        $this->addFilterSelect($viewName, 'iddiario', 'journals', 'iddiario', $selectJournals);

        // Channel filter
        $selectChannel = $this->codeModel->getAll('asientos', 'canal', 'canal');
        if (count($selectChannel) > 1) {
            $this->addFilterSelect($viewName, 'canal', 'channel', 'canal', $selectChannel);
        }

        // Buttons
        if ($this->permissions->allowUpdate) {
            $this->addLockButton($viewName);
            $this->addRenumberButton($viewName);
        }
    }

    /**
     * Creates and configures the concepts view
     *
     * @param string $viewName
     */
    protected function createViewsConcepts(string $viewName = 'ListConceptoPartida'): void
    {
        $this->addView($viewName, 'ConceptoPartida', 'predefined-concepts', 'fa-solid fa-indent');
        $this->addSearchFields(['codconcepto', 'descripcion']);
        $this->addOrderBy(['codconcepto'], 'code');
        $this->addOrderBy(['descripcion'], 'description', 1);
    }

    /**
     * Creates and configures the journals view
     *
     * @param string $viewName
     */
    protected function createViewsJournals(string $viewName = 'ListDiario'): void
    {
        $this->addView($viewName, 'Diario', 'journals', 'fa-solid fa-book');
        $this->addSearchFields(['descripcion']);
        $this->addOrderBy(['iddiario'], 'code');
        $this->addOrderBy(['descripcion'], 'description', 1);
    }

    /**
     * Creates and configures the not balanced entries view
     *
     * @param string $viewName
     */
    protected function createViewsNotBalanced(string $viewName = 'ListAsiento-not'): void
    {
        $ids = [];
        
        // Build SQL query based on database type
        if (Configuration::get('db_type') === 'postgresql') {
            $sql = 'SELECT partidas.idasiento, ABS(SUM(partidas.debe) - SUM(partidas.haber))'
                . ' FROM partidas GROUP BY 1 HAVING ABS(SUM(partidas.debe) - SUM(partidas.haber)) >= 0.01';
        } else {
            $sql = 'SELECT partidas.idasiento, ABS(SUM(partidas.debe) - SUM(partidas.haber))'
                . ' FROM partidas GROUP BY 1 HAVING ROUND(ABS(SUM(partidas.debe) - SUM(partidas.haber)), 2) >= 0.01';
        }

        // Execute query
        $results = $this->dataBase->select($sql);
        foreach ($results as $row) {
            $ids[] = $row['idasiento'];
        }
        
        // If no unbalanced entries, don't create the view
        if (empty($ids)) {
            return;
        }

        $this->addView($viewName, 'Asiento', 'unbalance', 'fa-solid fa-exclamation-circle');
        $this->addSearchFields(['concepto', 'documento', 'CAST(numero AS char(255))']);
        $this->addOrderBy(['fecha', 'idasiento'], 'date', 2);
        $this->addOrderBy(['numero', 'idasiento'], 'number');
        $this->addOrderBy(['importe', 'idasiento'], 'amount');

        // Filter for unbalanced entries
        $this->addFilterSelectWhere($viewName, 'status', [
            [
                'label' => Translator::trans('unbalance'),
                'where' => [new DataBaseWhere('idasiento', implode(',', $ids), 'IN')]
            ]
        ]);
    }

    /**
     * Add lock entries button
     *
     * @param string $viewName
     */
    protected function addLockButton(string $viewName): void
    {
        $this->addButton($viewName, [
            'action' => 'lock-entries',
            'confirm' => true,
            'icon' => 'fa-solid fa-lock',
            'label' => 'lock-entry'
        ]);
    }

    /**
     * Add renumber entries button
     *
     * @param string $viewName
     */
    protected function addRenumberButton(string $viewName): void
    {
        $this->addButton($viewName, [
            'action' => 'renumber',
            'icon' => 'fa-solid fa-sort-numeric-down',
            'label' => 'renumber',
            'type' => 'modal'
        ]);
    }

    /**
     * Execute actions before reading data
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'lock-entries':
                $this->lockEntriesAction();
                return true;

            case 'renumber':
                $this->renumberAction();
                return true;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Lock selected accounting entries
     */
    protected function lockEntriesAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            Translator::log()->warning('not-allowed-modify');
            return;
        }

        if (!$this->validateFormToken()) {
            return;
        }

        $codes = $this->request->request->getArray('codes');
        $model = $this->views[$this->active]->model;
        
        if (!is_array($codes) || empty($model)) {
            Translator::log()->warning('no-selected-item');
            return;
        }

        $this->dataBase->beginTransaction();
        foreach ($codes as $code) {
            if (!$model->loadFromCode($code)) {
                Translator::log()->error('record-not-found');
                continue;
            }

            if (!$model->editable) {
                continue;
            }

            $model->editable = false;
            if (!$model->save()) {
                Translator::log()->error('record-save-error');
                $this->dataBase->rollback();
                return;
            }
        }

        Translator::log()->notice('record-updated-correctly');
        $this->dataBase->commit();
        $model->clear();
    }

    /**
     * Renumber accounting entries for an exercise
     */
    protected function renumberAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            Translator::log()->warning('not-allowed-modify');
            return;
        }

        if (!$this->validateFormToken()) {
            return;
        }

        $this->dataBase->beginTransaction();
        $codejercicio = $this->request->input('exercise');
        
        if ($this->views['ListAsiento']->model->renumber($codejercicio)) {
            Translator::log()->notice('renumber-accounting-ok');
            $this->dataBase->commit();
            return;
        }

        $this->dataBase->rollback();
        Translator::log()->error('record-save-error');
    }

    /**
     * Get exercises for filter, optionally filtered by company
     *
     * @return array
     */
    private function getSelectExercise(): array
    {
        $companyFilter = $this->request->input('filteridempresa', 0);
        $exerciseFilter = $this->request->input('filtercodejercicio', '');
        
        $where = empty($companyFilter) ? [] : [new DataBaseWhere('idempresa', $companyFilter)];
        $result = $this->codeModel->getAll('ejercicios', 'codejercicio', 'nombre', true, $where);
        
        if (empty($exerciseFilter)) {
            return $result;
        }

        // Check if the selected exercise exists in the list
        foreach ($result as $exercise) {
            if ($exerciseFilter === $exercise->code) {
                return $result;
            }
        }

        // Remove invalid exercise filter
        $this->request->request->set('filtercodejercicio', '');
        return $result;
    }
}