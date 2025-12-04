<?php
/**
 * Este archivo es parte de ERPIA
 * Copyright (C) 2024-2025 ERPIA Team
 *
 * Este programa es software libre: puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General GNU Affero como
 * publicada por la Free Software Foundation, ya sea la versión 3 de la
 * Licencia, o (a su opción) cualquier versión posterior.
 *
 * Este programa se distribuye con la esperanza de que sea útil,
 * pero SIN NINGUNA GARANTÍA; sin siquiera la garantía implícita de
 * COMERCIABILIDAD o IDONEIDAD PARA UN PROPÓSITO PARTICULAR. Consulte la
 * Licencia Pública General GNU Affero para más detalles.
 *
 * Debería haber recibido una copia de la Licencia Pública General GNU Affero
 * junto con este programa. Si no es así, consulte <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controlador para editar un elemento individual del modelo Pais
 * 
 * Gestiona la información de países y muestra las provincias asociadas.
 */
class EditPais extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Pais';
    }

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'country';
        $pageData['icon'] = 'fa-solid fa-globe-americas';
        
        return $pageData;
    }

    /**
     * Configura las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewsProvince();
    }

    /**
     * Crea la vista de provincias del país
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsProvince(string $viewName = 'ListProvincia'): void
    {
        $this->addListView($viewName, 'Provincia', 'provinces', 'fa-solid fa-map-signs')
            ->addOrderBy(['provincia'], 'name')
            ->addOrderBy(['codpais'], 'country')
            ->addSearchFields(['provincia', 'codisoprov', 'alias'])
            ->disableColumn('country');
    }

    /**
     * Carga datos en una vista específica
     * 
     * @param string $viewName Nombre de la vista
     * @param BaseView $view Instancia de la vista
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListProvincia':
                $codigoPais = $this->getViewModelValue($this->getMainViewName(), 'codpais');
                $filtro = [new DataBaseWhere('codpais', $codigoPais)];
                $view->loadData('', $filtro);
                break;

            default:
                parent::loadData($viewName, $view);
        }
    }
}