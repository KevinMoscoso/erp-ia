<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controller to edit a single item from the AgenciaTransporte model
 *
 * @author ERPIA Team
 */
class EditAgenciaTransporte extends EditController
{
    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'AgenciaTransporte';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'warehouse';
        $pageConfig['title'] = 'carrier';
        $pageConfig['icon'] = 'fa-solid fa-truck';
        return $pageConfig;
    }
}