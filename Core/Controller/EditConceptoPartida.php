<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controller to edit a single item from the Concepto Partida model
 *
 * @author ERPIA Team
 */
class EditConceptoPartida extends EditController
{
    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'ConceptoPartida';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'accounting';
        $pageConfig['title'] = 'predefined-concepts';
        $pageConfig['icon'] = 'fa-solid fa-indent';
        return $pageConfig;
    }
}