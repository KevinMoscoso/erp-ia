<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\AjaxForms\SalesController;

/**
 * Description of EditAlbaranCliente
 *
 * @author ERPIA Team
 */
class EditAlbaranCliente extends SalesController
{
    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'AlbaranCliente';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'sales';
        $pageConfig['title'] = 'delivery-note';
        $pageConfig['icon'] = 'fa-solid fa-dolly-flatbed';
        $pageConfig['showonmenu'] = false;
        return $pageConfig;
    }
}