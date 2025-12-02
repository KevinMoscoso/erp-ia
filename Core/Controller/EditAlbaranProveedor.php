<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\AjaxForms\PurchasesController;

/**
 * Description of EditAlbaranProveedor
 *
 * @author ERPIA Team
 */
class EditAlbaranProveedor extends PurchasesController
{
    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'AlbaranProveedor';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'purchases';
        $pageConfig['title'] = 'delivery-note';
        $pageConfig['icon'] = 'fa-solid fa-dolly-flatbed';
        $pageConfig['showonmenu'] = false;
        return $pageConfig;
    }
}