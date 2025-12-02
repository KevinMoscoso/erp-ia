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
use ERPIA\Core\Lib\OperacionIVA;

/**
 * Controlador para editar un elemento individual del modelo Impuesto
 * 
 * Gestiona la configuración de impuestos, incluyendo zonas de excepción,
 * productos asociados y cuentas contables, con configuración de operaciones de IVA.
 */
class EditImpuesto extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Impuesto';
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
        $pageData['title'] = 'tax';
        $pageData['icon'] = 'fa-solid fa-plus-square';
        
        return $pageData;
    }

    /**
     * Configura las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewsZones();
        $this->createViewsProducts();
        $this->createViewsAccounts();
    }

    /**
     * Crea la vista de cuentas contables relacionadas
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsAccounts(string $viewName = 'ListSubcuenta'): void
    {
        $this->addListView($viewName, 'Subcuenta', 'subaccounts', 'fa-solid fa-folder-open')
            ->addOrderBy(['codejercicio', 'codsubcuenta'], 'code', 2)
            ->addOrderBy(['codejercicio', 'descripcion'], 'description')
            ->addOrderBy(['saldo'], 'balance')
            ->addSearchFields(['codsubcuenta', 'descripcion']);

        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
    }

    /**
     * Crea la vista de productos asociados al impuesto
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsProducts(string $viewName = 'ListProducto'): void
    {
        $this->addListView($viewName, 'Producto', 'products', 'fa-solid fa-cubes')
            ->addOrderBy(['referencia'], 'reference', 1)
            ->addOrderBy(['precio'], 'price')
            ->addOrderBy(['stockfis'], 'stock')
            ->addSearchFields(['referencia', 'descripcion', 'observaciones']);

        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
    }

    /**
     * Crea la vista de zonas de excepción
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsZones(string $viewName = 'EditImpuestoZona'): void
    {
        $this->addEditListView($viewName, 'ImpuestoZona', 'exceptions', 'fa-solid fa-globe-americas')
            ->disableColumn('tax')
            ->setInLine(true);
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
        $codigoImpuesto = $this->getViewModelValue($vistaPrincipal, 'codimpuesto');

        switch ($viewName) {
            case 'EditImpuestoZona':
                $filtro = [new DataBaseWhere('codimpuesto', $codigoImpuesto)];
                $view->loadData('', $filtro, ['prioridad' => 'DESC']);
                break;

            case 'ListProducto':
                $filtro = [new DataBaseWhere('codimpuesto', $codigoImpuesto)];
                $view->loadData('', $filtro);
                break;

            case 'ListSubcuenta':
                $codigos = [];
                $camposCuenta = ['codsubcuentarep', 'codsubcuentarepre', 'codsubcuentasop', 'codsubcuentasopre'];
                foreach ($camposCuenta as $campo) {
                    $valor = $this->getViewModelValue($vistaPrincipal, $campo);
                    if (!empty($valor)) {
                        $codigos[] = $valor;
                    }
                }

                if (empty($codigos)) {
                    $view->settings['active'] = false;
                    break;
                }

                $filtro = [new DataBaseWhere('codsubcuenta', implode(',', $codigos), 'IN')];
                $view->loadData('', $filtro);
                break;

            default:
                parent::loadData($viewName, $view);
                $this->loadOperations($viewName);
                break;
        }
    }

    /**
     * Configura los valores del widget de operación de IVA
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function loadOperations(string $viewName): void
    {
        $columna = $this->views[$viewName]->columnForName('operation');
        if ($columna && $columna->widget->getType() === 'select') {
            $columna->widget->setValuesFromArrayKeys(OperacionIVA::all(), true, true);
        }
    }
}