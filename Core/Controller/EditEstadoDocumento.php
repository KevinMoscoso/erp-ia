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
 * Controlador para editar un elemento individual del modelo EstadoDocumento
 * 
 * Permite gestionar estados de documentos y ver otros estados del mismo tipo.
 */
class EditEstadoDocumento extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'EstadoDocumento';
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
        $pageData['title'] = 'status-document';
        $pageData['icon'] = 'fa-solid fa-tag';
        
        return $pageData;
    }

    /**
     * Crea la vista de otros estados del mismo tipo de documento
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createOtherStatusView($viewName = 'ListEstadoDocumento')
    {
        $this->addListView($viewName, 'EstadoDocumento', 'document-states');

        // Desactivar botones
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Configura las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createOtherStatusView();
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
            case 'ListEstadoDocumento':
                $idEstado = $this->getViewModelValue($this->getMainViewName(), 'idestado');
                $tipoDocumento = $this->getViewModelValue($this->getMainViewName(), 'tipodoc');
                $filtros = [
                    new DataBaseWhere('tipodoc', $tipoDocumento),
                    new DataBaseWhere('idestado', $idEstado, '!='),
                ];
                $view->loadData('', $filtros, ['idestado' => 'ASC']);
                break;

            default:
                parent::loadData($viewName, $view);
        }
    }
}