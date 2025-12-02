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
 * Controlador para editar un elemento individual del modelo LogMessage
 * 
 * Permite ver los detalles de un mensaje de log y otros registros
 * relacionados de la misma dirección IP.
 */
class EditLogMessage extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'LogMessage';
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
        $pageData['title'] = 'log';
        $pageData['icon'] = 'fa-solid fa-file-medical-alt';
        
        return $pageData;
    }

    /**
     * Configura las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();

        $vistaPrincipal = $this->getMainViewName();
        $this->setSettings($vistaPrincipal, 'btnNew', false);
        $this->setSettings($vistaPrincipal, 'btnOptions', false);

        $this->createViewsOtherLogs();
        $this->setTabsPosition('bottom');
    }

    /**
     * Crea la vista de otros logs relacionados
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsOtherLogs(string $viewName = 'ListLogMessage')
    {
        $this->addListView($viewName, 'LogMessage', 'related', 'fa-solid fa-file-medical-alt')
            ->addSearchFields(['ip', 'message', 'uri'])
            ->addOrderBy(['time', 'id'], 'date', 2)
            ->addOrderBy(['level'], 'level');

        $this->setSettings($viewName, 'btnNew', false);
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
            case 'ListLogMessage':
                $idActual = $this->getViewModelValue($this->getMainViewName(), 'id');
                $direccionIP = $this->getViewModelValue($this->getMainViewName(), 'ip');
                $filtros = [
                    new DataBaseWhere('id', $idActual, '!='),
                    new DataBaseWhere('ip', $direccionIP)
                ];
                $view->loadData('', $filtros);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}