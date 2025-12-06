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
use ERPIA\Core\Request;
use ERPIA\Core\Config;
use ERPIA\Dinamic\Lib\AssetManager;
use ERPIA\Dinamic\Lib\ExportManager;

/**
 * View definition for its use in ExtendedControllers
 *
 * @author ERPIA Team
 */
class EditListView extends BaseView
{
    const DEFAULT_TEMPLATE = 'Master/EditListView.html.twig';
    const INLINE_TEMPLATE = 'Master/EditListViewInLine.html.twig';

    /**
     * Indicates if the view has been selected by the user.
     *
     * @var bool
     */
    public $selected;

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
        if ($this->count <= 0) {
            return true;
        }

        return $exportManager->addListModelPage(
            $this->model, 
            $this->where, 
            $this->order, 
            $this->offset, 
            $this->getColumns(), 
            $this->title
        );
    }

    /**
     * Load the data in the cursor property, according to the where filter specified.
     * Adds an empty row/model at the end of the loaded data.
     *
     * @param string $code
     * @param DataBaseWhere[] $where
     * @param array $order
     * @param int $offset
     * @param int $limit
     */
    public function loadData($code = '', $where = [], $order = [], $offset = -1, $limit = null)
    {
        if ($limit === null) {
            $limit = Config::get('item_limit', 50);
        }

        $this->offset = $offset < 0 ? $this->offset : $offset;
        $this->order = empty($order) ? $this->order : $order;

        $finalWhere = empty($where) ? $this->where : $where;
        $this->count = is_null($this->model) ? 0 : $this->model->count($finalWhere);

        if ($this->count > 0) {
            $this->cursor = $this->model->all($finalWhere, $this->order, $this->offset, $limit);
        }

        // Add an empty model at the end for inline editing
        if ($this->model !== null) {
            $emptyModel = clone $this->model;
            $this->cursor[] = $emptyModel;
        }

        $this->where = $finalWhere;
        if ($this->model !== null) {
            foreach (DataBaseWhere::getFieldsFilter($this->where) as $field => $value) {
                $this->model->{$field} = $value;
            }
        }
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
                foreach ($this->getColumns() as $group) {
                    $group->processFormData($this->model, $request);
                }
                $this->selected = $request->request->get('code');
                break;

            case 'load':
                $this->offset = (int)$request->request->get('offset', 0);
                break;
        }
    }

    /**
     * Sets edit mode to single line.
     *
     * @param bool $value
     * @return EditListView
     */
    public function setInLine(bool $value): EditListView
    {
        $this->template = $value ? static::INLINE_TEMPLATE : static::DEFAULT_TEMPLATE;
        return $this;
    }

    /**
     * Adds assets to the asset manager.
     */
    protected function assets(): void
    {
        $route = Config::get('route');
        AssetManager::addJs($route . '/Dinamic/Assets/JS/EditListView.js');
    }
}