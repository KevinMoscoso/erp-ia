<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Description of EditCronJob
 *
 * @author ERPIA Team
 */
class EditCronJob extends EditController
{
    public function getModelClassName(): string
    {
        return 'CronJob';
    }

    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'admin';
        $pageConfig['title'] = 'cron-job';
        $pageConfig['icon'] = 'fa-solid fa-cogs';
        return $pageConfig;
    }

    protected function createViews()
    {
        parent::createViews();

        $mainView = $this->getMainViewName();
        $this->setSettings($mainView, 'btnNew', false);
        $this->setSettings($mainView, 'btnOptions', false);

        $this->createLogsView();
        $this->setTabsPosition('bottom');
    }

    protected function createLogsView(string $viewName = 'ListLogMessage'): void
    {
        $this->addListView($viewName, 'LogMessage', 'related', 'fa-solid fa-file-medical-alt');
        $this->views[$viewName]->addSearchFields(['ip', 'message', 'uri']);
        $this->views[$viewName]->addOrderBy(['time', 'id'], 'date', 2);
        $this->setSettings($viewName, 'btnNew', false);
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListLogMessage':
                $jobName = $this->getViewModelValue($this->getMainViewName(), 'jobname');
                $conditions = [new DataBaseWhere('channel', $jobName)];
                $view->loadData('', $conditions);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}