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
use ERPIA\Core\Cache\DataCache;
use ERPIA\Core\Model\Base\BusinessDocument;
use ERPIA\Core\Request;
use ERPIA\Core\SessionManager;
use ERPIA\Core\Model\Base\ModelClass;
use ERPIA\Core\Config;
use ERPIA\Core\Translation;
use ERPIA\Dinamic\Lib\AssetManager;
use ERPIA\Dinamic\Lib\ExportManager;
use ERPIA\Dinamic\Lib\Widget\ColumnItem;
use ERPIA\Dinamic\Lib\Widget\RowStatus;
use ERPIA\Dinamic\Model\User;

/**
 * View definition for its use in ListController
 *
 * @author ERPIA Team
 */
class ListView extends BaseView
{
    use ListViewFiltersTrait;

    const DEFAULT_TEMPLATE = 'Master/ListView.html.twig';

    /** @var string */
    public $orderKey = '';

    /** @var array */
    public $orderOptions = [];

    /** @var string */
    public $query = '';

    /** @var array */
    public $searchFields = [];

    /** @var array */
    public $totalAmounts = [];

    /**
     * Adds a new color option to the list.
     *
     * @param string $fieldName
     * @param mixed $value
     * @param string $color
     * @param string $title
     * @return ListView
     */
    public function addColor(string $fieldName, $value, string $color, string $title = ''): ListView
    {
        if (!isset($this->rows['status'])) {
            $this->rows['status'] = new RowStatus([]);
        }

        $this->rows['status']->options[] = [
            'tag' => 'option',
            'children' => [],
            'color' => $color,
            'fieldname' => $fieldName,
            'text' => $value,
            'title' => $title
        ];

        return $this;
    }

    /**
     * Adds a field to the Order By list
     *
     * @param array $fields
     * @param string $label
     * @param int $default (0 = None, 1 = ASC, 2 = DESC)
     * @return ListView
     */
    public function addOrderBy(array $fields, string $label, int $default = 0): ListView
    {
        $key1 = count($this->orderOptions);
        $this->orderOptions[$key1] = [
            'fields' => $fields,
            'label' => Translation::translate($label),
            'type' => 'ASC'
        ];

        $key2 = count($this->orderOptions);
        $this->orderOptions[$key2] = [
            'fields' => $fields,
            'label' => Translation::translate($label),
            'type' => 'DESC'
        ];

        if ($default === 2) {
            $this->setSelectedOrderBy($key2);
        } elseif ($default === 1 || empty($this->order)) {
            $this->setSelectedOrderBy($key1);
        }

        return $this;
    }

    /**
     * Adds a list of fields to the search in the ListView.
     * To use integer columns, use CAST(columnName AS CHAR(50)).
     *
     * @param array $fields
     * @return ListView
     */
    public function addSearchFields(array $fields): ListView
    {
        foreach ($fields as $field) {
            $this->searchFields[] = $field;
        }

        return $this;
    }

    /**
     * Generates the URL for the new button with current filters applied.
     *
     * @return string
     */
    public function btnNewUrl(): string
    {
        $url = empty($this->model) ? '' : $this->model->url('new');
        $params = [];
        foreach (DataBaseWhere::getFieldsFilter($this->where) as $key => $value) {
            if ($value !== false) {
                $params[] = $key . '=' . $value;
            }
        }

        return empty($params) ? $url : $url . '?' . implode('&', $params);
    }

    /**
     * Method to export the view data.
     *
     * @param ExportManager $exportManager
     * @param mixed $codes
     *
     * @return bool
     */
    public function export(&$exportManager, $codes): bool
    {
        // no data
        if ($this->count < 1) {
            return true;
        }

        // selected items?
        if (is_array($codes) && count($codes) > 0 && $this->model instanceof ModelClass) {
            foreach ($this->cursor as $model) {
                if (!in_array($model->primaryKeyValue(), $codes)) {
                    continue;
                }

                if ($model instanceof BusinessDocument) {
                    $exportManager->addBusinessDocPage($model);
                    continue;
                }

                $exportManager->addModelPage($model, $this->getColumns(), $this->title);
            }
            return false;
        }

        // print list
        $exportManager->addListModelPage(
            $this->model, 
            $this->where, 
            $this->order, 
            $this->offset, 
            $this->getColumns(), 
            $this->title
        );

        // print totals
        if (!empty($this->totalAmounts)) {
            $total = [];
            foreach ($this->totalAmounts as $key => $value) {
                $total[$key] = $value['total'];
            }
            $exportManager->addTablePage(array_keys($total), [$total]);
        }

        return true;
    }

    /**
     * @return ColumnItem[]
     */
    public function getColumns(): array
    {
        foreach ($this->columns as $group) {
            return $group->columns;
        }

        return [];
    }

    /**
     * Loads the data in the cursor property, according to the where filter specified.
     *
     * @param string $code
     * @param DataBaseWhere[] $where
     * @param array $order
     * @param int $offset
     * @param int $limit
     */
    public function loadData($code = '', $where = [], $order = [], $offset = -1, $limit = -1)
    {
        $this->offset = $offset < 0 ? $this->offset : $offset;
        $this->order = empty($order) ? $this->order : $order;
        $this->where = array_merge($where, $this->where);
        $this->count = is_null($this->model) ? 0 : $this->model->count($this->where);

        // avoid overflow
        if ($this->offset > $this->count) {
            $this->offset = 0;
        }

        // check limit
        if ($limit < 0) {
            $limit = $this->settings['itemLimit'];
        } elseif ($limit != $this->settings['itemLimit']) {
            $this->settings['itemLimit'] = $limit;
        }

        // needed when mega-search force data reload
        $this->cursor = [];
        if ($this->count > 0) {
            $this->cursor = $this->model->all($this->where, $this->order, $this->offset, $limit);
            $this->loadTotalAmounts();
        }
    }

    /**
     * @param User|false $user
     */
    public function loadPageOptions($user = false)
    {
        parent::loadPageOptions($user);

        // load saved filters
        $where = $this->getPageWhere($user);
        $this->loadSavedFilters($where);
    }

    /**
     * Process form data needed.
     *
     * @param Request $request
     * @param string $case
     */
    public function processFormData($request, $case)
    {
        switch ($case) {
            case 'edit':
                $name = $this->settings['modalInsert'] ?? '';
                if (empty($name)) {
                    break;
                }
                $modals = $this->getModals();
                foreach ($modals[$name]->columns as $group) {
                    $group->processFormData($this->model, $request);
                }
                break;

            case 'load':
                $this->sortFilters();
                $this->processFormDataLoad($request);
                break;

            case 'preload':
                $this->sortFilters();
                foreach ($this->filters as $filter) {
                    $filter->getDataBaseWhere($this->where);
                }
                break;
        }
    }

    /**
     * Adds assets to the asset manager.
     */
    protected function assets(): void
    {
        $route = Config::get('route');
        AssetManager::addJs($route . '/Dinamic/Assets/JS/ListView.js?v=2');
    }

    /**
     * Checks and establishes the selected value in the Order By
     *
     * @param string $orderKey
     */
    protected function setSelectedOrderBy(string $orderKey): void
    {
        if (isset($this->orderOptions[$orderKey])) {
            $this->order = [];
            $option = $this->orderOptions[$orderKey];
            foreach ($option['fields'] as $field) {
                $this->order[$field] = $option['type'];
            }

            $this->orderKey = $orderKey;
        }
    }

    /**
     * Loads total amounts for numeric columns.
     */
    private function loadTotalAmounts(): void
    {
        if (count($this->cursor) <= 1) {
            return;
        }

        $modelFields = $this->model->getModelFields();
        foreach ($this->getColumns() as $col) {
            // if the column is hidden or not of a type to show totals, skip
            if ($col->hidden() || !$col->widget->showTableTotals()) {
                continue;
            }

            // if the column does not belong to the model, skip
            if (!array_key_exists($col->widget->fieldname, $modelFields)) {
                continue;
            }

            // calculate page total
            $pageTotalAmount = 0;
            foreach ($this->cursor as $model) {
                $pageTotalAmount += $model->{$col->widget->fieldname};
            }

            $this->totalAmounts[$col->widget->fieldname] = [
                'title' => $col->title,
                'page' => $pageTotalAmount,
                'total' => $this->model->totalSum($col->widget->fieldname, $this->where)
            ];
        }
    }

    /**
     * Process form data for load case.
     *
     * @param Request $request
     */
    private function processFormDataLoad(Request $request)
    {
        $this->offset = (int)$request->request->get('offset', 0);
        $this->setSelectedOrderBy($request->request->get('order', ''));

        // query
        $this->query = $request->request->get('query', '');
        if ('' !== $this->query) {
            $fields = implode('|', $this->searchFields);
            $this->where[] = new DataBaseWhere($fields, $this->sanitizeInput($this->query), 'XLIKE');
        }

        // saved filter selected?
        $this->pageFilterKey = $request->request->get('loadfilter', 0);
        if ($this->pageFilterKey) {
            $filterLoad = [];
            // load values into request
            foreach ($this->pageFilters as $item) {
                if ($item->id == $this->pageFilterKey) {
                    $request->request->add($item->filters);
                    $filterLoad = $item->filters;
                    break;
                }
            }
            // apply request values to filters
            foreach ($this->filters as $filter) {
                $key = 'filter' . $filter->key;
                $filter->readonly = true;
                if (array_key_exists($key, $filterLoad)) {
                    $filter->setValueFromRequest($request);
                    if ($filter->getDataBaseWhere($this->where)) {
                        $this->showFilters = true;
                    }
                }
            }
            return;
        }

        // if request is GET, get filters from cache
        $cacheKey = 'filters-' . SessionManager::getControllerName() . '-' . $this->getViewName() . '-' . $request->cookie('erpia_user');
        if ($request->isMethod('GET')) {
            $filtersCache = DataCache::get($cacheKey);
            if ($filtersCache) {
                // create filter values in request to reuse existing methods
                foreach ($filtersCache as $filter) {
                    $request->request->set('filter' . $filter->key, $filter->getValue());
                }
            }
        }

        // filters
        foreach ($this->filters as $filter) {
            $filter->setValueFromRequest($request);
            if ($filter->getDataBaseWhere($this->where)) {
                $this->showFilters = true;
            }
        }

        if ($request->isMethod('POST')) {
            // save filters to cache
            DataCache::set($cacheKey, $this->filters);
        }
    }

    /**
     * Sanitize input string.
     *
     * @param string $input
     * @return string
     */
    private function sanitizeInput(string $input): string
    {
        return strip_tags($input);
    }
}