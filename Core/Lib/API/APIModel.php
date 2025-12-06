<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2025 ERPIA Team
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

namespace ERPIA\Core\Lib\API;

use Exception;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\API\Base\APIResourceClass;
use ERPIA\Core\Response;
use ERPIA\Core\Template\ModelClass;
use ERPIA\Core\Tools;

/**
 * APIModel is the class for any API Model Resource in Dinamic/Model folder.
 *
 * @author ERPIA Team
 */
class APIModel extends APIResourceClass
{
    /**
     * ModelClass object.
     *
     * @var ModelClass
     */
    private $modelInstance;

    /**
     * Process the DELETE request.
     *
     * @return bool
     */
    public function doDELETE(): bool
    {
        if (empty($this->parameters) || false === $this->modelInstance->load($this->parameters[0])) {
            $this->setError(Tools::trans('record-not-found'), null, Response::HTTP_NOT_FOUND);
            return false;
        }

        if ($this->modelInstance->delete()) {
            $this->setOk(Tools::trans('record-deleted-successfully'), $this->modelInstance->toArray());
            return true;
        }

        $this->setError(Tools::trans('record-delete-error'));
        return false;
    }

    /**
     * Process the GET request.
     *
     * @return bool
     */
    public function doGET(): bool
    {
        // List all records
        if (empty($this->parameters)) {
            return $this->listAllRecords();
        }

        // Get model schema
        if ($this->parameters[0] === 'schema') {
            $schema = [];
            foreach ($this->modelInstance->getModelFields() as $fieldName => $fieldData) {
                $schema[$fieldName] = [
                    'type' => $fieldData['type'],
                    'default' => $fieldData['default'],
                    'nullable' => $fieldData['is_nullable']
                ];
            }
            $this->returnResult($schema);
            return true;
        }

        // Get single record
        if (false === $this->modelInstance->load($this->parameters[0])) {
            $this->setError(Tools::trans('record-not-found'), null, Response::HTTP_NOT_FOUND);
            return false;
        }

        $this->returnResult($this->modelInstance->toArray(true));
        return true;
    }

    /**
     * Process the POST (create) request.
     *
     * @return bool
     */
    public function doPOST(): bool
    {
        $primaryKey = $this->modelInstance->primaryColumn();
        $requestData = $this->request->request->all();

        $firstParam = empty($this->parameters) ? '' : $this->parameters[0];
        $recordCode = $requestData[$primaryKey] ?? $firstParam;
        
        // Check for duplicate
        if ($this->modelInstance->load($recordCode)) {
            $this->setError(Tools::trans('duplicate-record'), $this->modelInstance->toArray());
            return false;
        }
        
        if (empty($requestData)) {
            $this->setError(Tools::trans('no-data-received'));
            return false;
        }

        // Set model values
        foreach ($requestData as $field => $value) {
            $this->modelInstance->{$field} = $value === 'null' ? null : $value;
        }

        return $this->saveModelRecord();
    }

    /**
     * Process the PUT (update) request.
     *
     * @return bool
     */
    public function doPUT(): bool
    {
        $primaryKey = $this->modelInstance->primaryColumn();
        $requestData = $this->request->request->all();

        $firstParam = empty($this->parameters) ? '' : $this->parameters[0];
        $recordCode = $requestData[$primaryKey] ?? $firstParam;
        
        // Check if record exists
        if (false === $this->modelInstance->load($recordCode)) {
            $this->setError(Tools::trans('record-not-found'), null, Response::HTTP_NOT_FOUND);
            return false;
        }
        
        if (empty($requestData)) {
            $this->setError(Tools::trans('no-data-received'));
            return false;
        }

        // Update model values
        foreach ($requestData as $field => $value) {
            $this->modelInstance->{$field} = $value === 'null' ? null : $value;
        }

        return $this->saveModelRecord();
    }

    /**
     * Returns an associative array with the resources.
     *
     * @return array
     */
    public function getResources(): array
    {
        return $this->scanModelsDirectory('Model');
    }

    /**
     * Process the model resource.
     *
     * @param string $resourceName
     * @return bool
     */
    public function processResource(string $resourceName): bool
    {
        try {
            $modelClassName = 'ERPIA\\Dinamic\\Model\\' . $resourceName;
            $this->modelInstance = new $modelClassName();

            return parent::processResource($resourceName);
        } catch (Exception $exception) {
            $this->setError('API-ERROR: ' . $exception->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
            return false;
        }
    }

    /**
     * List all records with filters, pagination and sorting.
     *
     * @return bool
     */
    protected function listAllRecords(): bool
    {
        $filters = $this->request->query->getArray('filter');
        $limit = $this->request->query->getInt('limit', 50);
        $offset = $this->request->query->getInt('offset', 0);
        $operations = $this->request->query->getArray('operation');
        $sorting = $this->request->query->getArray('sort');

        $whereClauses = $this->buildWhereConditions($filters, $operations);
        
        $records = [];
        foreach ($this->modelInstance->all($whereClauses, $sorting, $offset, $limit) as $item) {
            $records[] = $item->toArray(true);
        }

        $totalCount = $this->modelInstance->count($whereClauses);
        $this->response->header('X-Total-Count', $totalCount);

        $this->returnResult($records);
        return true;
    }

    /**
     * Build DataBaseWhere conditions from filters.
     *
     * @param array $filters
     * @param array $operations
     * @param string $defaultOp
     * @return DataBaseWhere[]
     */
    private function buildWhereConditions(array $filters, array $operations, string $defaultOp = 'AND'): array
    {
        $whereConditions = [];
        
        foreach ($filters as $field => $value) {
            $actualField = $field;
            $operator = '=';
            $logicalOp = $operations[$field] ?? $defaultOp;

            // Handle special operators
            if (substr($field, -3) === '_gt') {
                $actualField = substr($field, 0, -3);
                $operator = '>';
            } elseif (substr($field, -3) === '_lt') {
                $actualField = substr($field, 0, -3);
                $operator = '<';
            } elseif (substr($field, -3) === '_is') {
                $actualField = substr($field, 0, -3);
                $operator = 'IS';
            } elseif (substr($field, -4) === '_gte') {
                $actualField = substr($field, 0, -4);
                $operator = '>=';
            } elseif (substr($field, -4) === '_lte') {
                $actualField = substr($field, 0, -4);
                $operator = '<=';
            } elseif (substr($field, -4) === '_neq') {
                $actualField = substr($field, 0, -4);
                $operator = '!=';
            } elseif (substr($field, -5) === '_null') {
                $actualField = substr($field, 0, -5);
                $operator = 'IS';
                $value = null;
            } elseif (substr($field, -8) === '_notnull') {
                $actualField = substr($field, 0, -8);
                $operator = 'IS NOT';
                $value = null;
            } elseif (substr($field, -5) === '_like') {
                $actualField = substr($field, 0, -5);
                $operator = 'LIKE';
            } elseif (substr($field, -6) === '_isnot') {
                $actualField = substr($field, 0, -6);
                $operator = 'IS NOT';
            }

            $whereConditions[] = new DataBaseWhere($actualField, $value, $operator, $logicalOp);
        }

        return $whereConditions;
    }

    /**
     * Scan models directory for available resources.
     *
     * @param string $directory
     * @return array
     */
    private function scanModelsDirectory(string $directory): array
    {
        $resources = [];
        $modelsPath = ERPIA_PATH . '/Dinamic/' . $directory . '/';
        
        if (!is_dir($modelsPath)) {
            return $resources;
        }

        foreach (scandir($modelsPath, SCANDIR_SORT_ASCENDING) as $file) {
            if (substr($file, -4) === '.php') {
                $modelName = substr($file, 0, -4);
                $pluralName = $this->makePlural($modelName);
                $resources[$pluralName] = $this->setResource($modelName);
            }
        }

        return $resources;
    }

    /**
     * Convert singular model name to plural.
     *
     * @param string $text
     * @return string
     */
    private function makePlural(string $text): string
    {
        $lowerText = strtolower($text);
        
        if (substr($lowerText, -1) === 's') {
            return $lowerText;
        }

        if (substr($lowerText, -3) === 'ser' || substr($lowerText, -4) === 'tion') {
            return $lowerText . 's';
        }

        $lastChar = substr($lowerText, -1);
        if (in_array($lastChar, ['a', 'e', 'i', 'o', 'u', 'k'], false)) {
            return $lowerText . 's';
        }

        return $lowerText . 'es';
    }

    /**
     * Save the model record and handle response.
     *
     * @return bool
     */
    private function saveModelRecord(): bool
    {
        if ($this->modelInstance->save()) {
            $this->setOk(Tools::trans('record-saved-successfully'), $this->modelInstance->toArray(true));
            return true;
        }

        $errorMessage = Tools::trans('record-save-error');
        $logMessages = Tools::log()->read('', ['critical', 'error', 'info', 'notice', 'warning']);
        
        foreach ($logMessages as $log) {
            $errorMessage .= ' - ' . $log['message'];
        }

        $this->setError($errorMessage, $this->modelInstance->toArray(true));
        return false;
    }
}