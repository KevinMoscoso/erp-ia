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
 * Controlador para editar un elemento individual del modelo Diario
 * 
 * Proporciona funcionalidad para gestionar diarios contables y visualizar
 * los asientos asociados a cada diario.
 */
class EditDiario extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Diario';
    }

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'accounting';
        $pageData['title'] = 'journal';
        $pageData['icon'] = 'fa-solid fa-book';
        
        return $pageData;
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createViewsEntries();
        $this->setTabsPosition('bottom');
    }

    /**
     * Crea la vista de listado de asientos contables
     * 
     * @param string $viewName Nombre de la vista (por defecto 'ListAsiento')
     */
    protected function createViewsEntries(string $viewName = 'ListAsiento')
    {
        $this->addListView($viewName, 'Asiento', 'accounting-entry');
        
        // Configurar ordenamientos
        $this->views[$viewName]->addOrderBy(['fecha'], 'date', 2);
        $this->views[$viewName]->addOrderBy(['importe'], 'amount');
        
        // Configurar campo de búsqueda
        $this->views[$viewName]->addSearchFields(['concepto']);
        
        // Ocultar columna de diario
        $this->views[$viewName]->disableColumn('journal');
        
        // Deshabilitar botón de eliminar
        $this->setSettings($viewName, 'btnDelete', false);
    }

    /**
     * Carga los datos en una vista específica
     * 
     * @param string $viewName Nombre de la vista
     * @param BaseView $view Instancia de la vista
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListAsiento':
                $idDiario = $this->getViewModelValue($this->getMainViewName(), 'iddiario');
                if (!empty($idDiario)) {
                    $filtro = [new DataBaseWhere('iddiario', $idDiario)];
                    $view->loadData('', $filtro);
                }
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}