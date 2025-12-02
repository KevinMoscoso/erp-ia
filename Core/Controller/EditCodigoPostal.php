<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * EditCodigoPostal
 *
 * @author ERPIA Team
 */
class EditCodigoPostal extends EditController
{
    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'CodigoPostal';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'admin';
        $pageConfig['title'] = 'zip-code';
        $pageConfig['icon'] = 'fa-solid fa-map-pin';
        return $pageConfig;
    }
}