<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\SystemTools;
use ERPIA\Dinamic\Model\ApiAccess;

/**
 * Controller to edit a single item from the ApiKey model.
 *
 * @author ERPIA Team
 */
class EditApiKey extends EditController
{
    /**
     * Returns the current access rules for the API key
     * @return array
     */
    public function getAccessRules(): array
    {
        $rules = [];
        foreach ($this->getResources() as $resource) {
            $rules[$resource] = [
                'allowget' => false,
                'allowpost' => false,
                'allowput' => false,
                'allowdelete' => false
            ];
        }

        $apiKeyId = $this->request->query('code');
        $conditions = [new DataBaseWhere('idapikey', $apiKeyId)];
        foreach (ApiAccess::all($conditions) as $access) {
            $rules[$access->resource]['allowget'] = $access->allowget;
            $rules[$access->resource]['allowpost'] = $access->allowpost;
            $rules[$access->resource]['allowput'] = $access->allowput;
            $rules[$access->resource]['allowdelete'] = $access->allowdelete;
        }

        return $rules;
    }

    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'ApiKey';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'admin';
        $pageConfig['title'] = 'api-key';
        $pageConfig['icon'] = 'fa-solid fa-key';
        return $pageConfig;
    }

    /**
     * Creates all views for the controller
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
        $this->createAccessRulesView();
    }

    /**
     * Creates the API access rules view
     * @param string $viewName
     */
    protected function createAccessRulesView(string $viewName = 'ApiAccess'): void
    {
        $this->addHtmlView($viewName, 'Tab/ApiAccess', 'ApiAccess', 'rules', 'fa-solid fa-check-square');
    }

    /**
     * Handles the edit-rules action to update access rules
     * @return bool
     */
    protected function editRulesAction(): bool
    {
        // Check user permissions
        if (!$this->permissions->allowUpdate) {
            SystemTools::log()->warning('not-allowed-update');
            return true;
        } elseif (!$this->validateFormToken()) {
            return true;
        }

        $allowGet = $this->request->request->getArray('allowget', false);
        $allowPut = $this->request->request->getArray('allowput', false);
        $allowPost = $this->request->request->getArray('allowpost', false);
        $allowDelete = $this->request->request->getArray('allowdelete', false);

        $apiKeyId = $this->request->query('code');
        $conditions = [new DataBaseWhere('idapikey', $apiKeyId)];
        $existingRules = ApiAccess::all($conditions);

        // Update existing rules
        foreach ($existingRules as $access) {
            $access->allowget = in_array($access->resource, $allowGet);
            $access->allowput = in_array($access->resource, $allowPut);
            $access->allowpost = in_array($access->resource, $allowPost);
            $access->allowdelete = in_array($access->resource, $allowDelete);
            $access->save();
        }

        // Add new rules for resources not previously configured
        foreach ($allowGet as $resource) {
            $found = false;
            foreach ($existingRules as $rule) {
                if ($rule->resource === $resource) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                continue;
            }

            $newAccess = new ApiAccess();
            $newAccess->idapikey = $apiKeyId;
            $newAccess->resource = $resource;
            $newAccess->allowget = in_array($resource, $allowGet);
            $newAccess->allowput = in_array($resource, $allowPut);
            $newAccess->allowpost = in_array($resource, $allowPost);
            $newAccess->allowdelete = in_array($resource, $allowDelete);
            $newAccess->save();
        }

        SystemTools::log()->notice('record-updated-correctly');
        return true;
    }

    /**
     * Executes actions before data reading
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        if ($action == 'edit-rules') {
            return $this->editRulesAction();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Retrieves all available API resources
     * @return array
     */
    protected function getResources(): array
    {
        $resources = [];
        $apiPath = ERPIA_FOLDER . DIRECTORY_SEPARATOR . 'Dinamic' . DIRECTORY_SEPARATOR . 'Lib' . DIRECTORY_SEPARATOR . 'API';

        foreach (scandir($apiPath, SCANDIR_SORT_NONE) as $file) {
            if (substr($file, -4) !== '.php') {
                continue;
            }

            $className = '\\ERPIA\\Dinamic\\Lib\\API\\' . substr($file, 0, -4);
            $apiInstance = new $className($this->response, $this->request, []);
            if (isset($apiInstance) && method_exists($apiInstance, 'getResources')) {
                foreach ($apiInstance->getResources() as $name => $data) {
                    $resources[] = $name;
                }
            }
        }

        // Add custom and plugin resources
        $resources = array_merge($resources, \ERPIA\Core\Controller\ApiRoot::getCustomResources());
        sort($resources);
        return $resources;
    }

    /**
     * Loads data for each view
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainView = $this->getMainViewName();
        if ($viewName === $mainView) {
            parent::loadData($viewName, $view);
            if (!$view->model->exists()) {
                $view->model->nick = $this->user->nick;
            } elseif ($view->model->fullaccess) {
                // Hide permissions tab for full-access keys
                $this->setSettings('ApiAccess', 'active', false);
            }
        }
    }
}