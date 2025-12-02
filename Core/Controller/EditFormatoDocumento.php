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

use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controlador para editar un elemento individual del modelo FormatoDocumento
 * 
 * Gestiona los formatos de impresión de documentos en el sistema.
 */
class EditFormatoDocumento extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'FormatoDocumento';
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
        $pageData['title'] = 'printing-format';
        $pageData['icon'] = 'fa-solid fa-print';
        
        return $pageData;
    }

    /**
     * Carga datos en una vista específica
     * 
     * @param string $viewName Nombre de la vista
     * @param BaseView $view Instancia de la vista
     */
    protected function loadData($viewName, $view)
    {
        $vistaPrincipal = $this->getMainViewName();

        switch ($viewName) {
            case $vistaPrincipal:
                parent::loadData($viewName, $view);

                // Desactivar botones de opciones e imprimir
                $this->setSettings($viewName, 'btnOptions', false);
                $this->setSettings($viewName, 'btnPrint', false);

                // Ocultar columna de empresa si solo hay una
                if ($this->empresa->count() < 2) {
                    $view->disableColumn('company');
                }
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}