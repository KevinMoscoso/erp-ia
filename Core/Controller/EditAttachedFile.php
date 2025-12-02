<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controller to edit a single item from the AttachedFile model
 *
 * @author ERPIA Team
 */
class EditAttachedFile extends EditController
{
    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'AttachedFile';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'admin';
        $pageConfig['title'] = 'attached-file';
        $pageConfig['icon'] = 'fa-solid fa-paperclip';
        return $pageConfig;
    }

    /**
     * Creates all views for the controller
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createPreviewView();
        $this->createRelationsView();
        $this->setTabsPosition('bottom');
    }

    /**
     * Creates the file preview view
     * @param string $viewName
     */
    protected function createPreviewView(string $viewName = 'preview'): void
    {
        $this->addHtmlView($viewName, 'Tab/AttachedFilePreview', 'AttachedFile', 'file', 'fa-solid fa-eye');
    }

    /**
     * Creates the file relations view
     * @param string $viewName
     */
    protected function createRelationsView(string $viewName = 'ListAttachedFileRelation'): void
    {
        $this->addListView($viewName, 'AttachedFileRelation', 'related', 'fa-solid fa-copy');
        $this->views[$viewName]->addSearchFields(['observations']);
        $this->views[$viewName]->addOrderBy(['creationdate'], 'date', 2);
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Loads data for each view
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListAttachedFileRelation':
                $fileId = $this->getModel()->primaryColumnValue();
                $conditions = [new DataBaseWhere('idfile', $fileId)];
                $view->loadData('', $conditions);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}