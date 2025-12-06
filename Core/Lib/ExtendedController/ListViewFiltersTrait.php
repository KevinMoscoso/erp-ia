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
use ERPIA\Core\Lib\ListFilter\BaseFilter;
use ERPIA\Core\Model\User;
use ERPIA\Core\Request;
use ERPIA\Dinamic\Lib\ListFilter\AutocompleteFilter;
use ERPIA\Dinamic\Lib\ListFilter\CheckboxFilter;
use ERPIA\Dinamic\Lib\ListFilter\DateFilter;
use ERPIA\Dinamic\Lib\ListFilter\NumberFilter;
use ERPIA\Dinamic\Lib\ListFilter\PeriodFilter;
use ERPIA\Dinamic\Lib\ListFilter\SelectFilter;
use ERPIA\Dinamic\Lib\ListFilter\SelectWhereFilter;
use ERPIA\Dinamic\Model\PageFilter;

/**
 * Description of ListViewFiltersTrait
 *
 * @author ERPIA Team
 */
trait ListViewFiltersTrait
{
    /**
     * Filter configuration preset by the user
     *
     * @var BaseFilter[]
     */
    public $filters = [];

    /**
     * Predefined filter values selected
     *
     * @var int
     */
    public $pageFilterKey = 0;

    /**
     * List of predefined filter values
     *
     * @var PageFilter[]
     */
    public $pageFilters = [];

    /**
     * @var bool
     */
    public $showFilters = false;

    /**
     * Get the view name for the current list view.
     * 
     * @return string
     */
    abstract public function getViewName(): string;

    /**
     * Add an autocomplete type filter to the ListView.
     *
     * @param string $key
     * @param string $label
     * @param string $field
     * @param string $table
     * @param string $fieldCode
     * @param string $fieldTitle
     * @param array $where
     * @return ListView
     */
    public function addFilterAutocomplete(string $key, string $label, string $field, string $table, string $fieldCode = '', string $fieldTitle = '', array $where = []): ListView
    {
        $this->filters[$key] = new AutocompleteFilter($key, $field, $label, $table, $fieldCode, $fieldTitle, $where);

        return $this;
    }

    /**
     * Adds a boolean condition type filter to the ListView.
     *
     * @param string $key
     * @param string $label
     * @param string $field
     * @param string $operation
     * @param mixed $matchValue
     * @param array $default
     * @return ListView
     */
    public function addFilterCheckbox(string $key, string $label = '', string $field = '', string $operation = '=', $matchValue = true, array $default = []): ListView
    {
        $this->filters[$key] = new CheckboxFilter($key, $field, $label, $operation, $matchValue, $default);

        return $this;
    }

    /**
     * Adds a date type filter to the ListView.
     *
     * @param string $key
     * @param string $label
     * @param string $field
     * @param string $operation
     * @param bool $dateTime
     * @return ListView
     */
    public function addFilterDatePicker(string $key, string $label = '', string $field = '', string $operation = '>=', bool $dateTime = false): ListView
    {
        $this->filters[$key] = new DateFilter($key, $field, $label, $operation, $dateTime);

        return $this;
    }

    /**
     * Adds a numeric type filter to the ListView.
     *
     * @param string $key
     * @param string $label
     * @param string $field
     * @param string $operation
     * @return ListView
     */
    public function addFilterNumber(string $key, string $label = '', string $field = '', string $operation = '>='): ListView
    {
        $this->filters[$key] = new NumberFilter($key, $field, $label, $operation);

        return $this;
    }

    /**
     * Adds a period type filter to the ListView.
     * (period + start date + end date)
     *
     * @param string $key
     * @param string $label
     * @param string $field
     * @param bool $dateTime
     * @return ListView
     */
    public function addFilterPeriod(string $key, string $label, string $field, bool $dateTime = false): ListView
    {
        $this->filters[$key] = new PeriodFilter($key, $field, $label, $dateTime);

        return $this;
    }

    /**
     * Add a select type filter to a ListView.
     *
     * @param string $key
     * @param string $label
     * @param string $field
     * @param array $values
     * @return ListView
     */
    public function addFilterSelect(string $key, string $label, string $field, array $values = []): ListView
    {
        $this->filters[$key] = new SelectFilter($key, $field, $label, $values);

        return $this;
    }

    /**
     * Add a select where type filter to a ListView.
     *
     * @param string $key
     * @param array $values
     * @param string $label
     *
     * Example of values:
     *   [
     *    ['label' => 'Only active', 'where' => [new DataBaseWhere('suspended', false)]]
     *    ['label' => 'Only suspended', 'where' => [new DataBaseWhere('suspended', true)]]
     *    ['label' => 'All records', 'where' => []],
     *   ]
     * @return ListView
     */
    public function addFilterSelectWhere(string $key, array $values, string $label = ''): ListView
    {
        $this->filters[$key] = new SelectWhereFilter($key, $values, $label);

        return $this;
    }

    /**
     * Removes a saved user filter.
     *
     * @param string $idFilter
     * @return bool
     */
    public function deletePageFilter(string $idFilter): bool
    {
        $pageFilter = new PageFilter();
        if ($pageFilter->loadFromCode($idFilter) && $pageFilter->remove()) {
            // remove from the list
            unset($this->pageFilters[$idFilter]);

            return true;
        }

        return false;
    }

    /**
     * Save filter values for user.
     *
     * @param Request $request
     * @param User $user
     * @return int
     */
    public function savePageFilter(Request $request, User $user): int
    {
        $pageFilter = new PageFilter();
        // Set values data filter
        foreach ($this->filters as $filter) {
            $name = $filter->getName();
            $value = $request->request->get($name);
            if ($value !== null) {
                $pageFilter->filters[$name] = $value;
            }
        }

        // If filters values its empty, don't save filter
        if (empty($pageFilter->filters)) {
            return 0;
        }

        // Set basic data and save filter
        $pageFilter->id = $request->request->get('filter-id');
        $pageFilter->description = $request->request->get('filter-description', '');
        $pageFilter->name = explode('-', $this->getViewName())[0];
        $pageFilter->username = $user->username;

        // Save and return if it's all ok
        if ($pageFilter->store()) {
            $this->pageFilters[] = $pageFilter;
            return $pageFilter->id;
        }

        return 0;
    }

    /**
     * Load saved filters from database.
     *
     * @param DataBaseWhere[] $where
     */
    private function loadSavedFilters(array $where): void
    {
        $pageFilter = new PageFilter();
        $orderBy = ['username' => 'ASC', 'description' => 'ASC'];
        foreach ($pageFilter->all($where, $orderBy) as $filter) {
            $this->pageFilters[$filter->id] = $filter;
        }
    }

    /**
     * Sort filters by order number.
     */
    private function sortFilters(): void
    {
        uasort($this->filters, function ($filter1, $filter2) {
            if ($filter1->orderNumber === $filter2->orderNumber) {
                return 0;
            }

            return $filter1->orderNumber > $filter2->orderNumber ? 1 : -1;
        });
    }
}