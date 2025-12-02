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
use ERPIA\Core\DataSrc\FormasPago;
use ERPIA\Core\DataSrc\Retenciones;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\ExtendedController\BaseView;
use ERPIA\Dinamic\Lib\ExtendedController\EditController;
use ERPIA\Dinamic\Model\Cliente;

/**
 * Controlador para editar un elemento individual del modelo GrupoClientes
 * 
 * Gestiona grupos de clientes y permite asociar o desasociar clientes
 * mediante acciones masivas con filtros avanzados.
 */
class EditGrupoClientes extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'GrupoClientes';
    }

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'sales';
        $pageData['title'] = 'customer-group';
        $pageData['icon'] = 'fa-solid fa-users-cog';
        
        return $pageData;
    }

    /**
     * Agrega clientes seleccionados al grupo actual
     */
    protected function addCustomerAction()
    {
        $codigos = $this->request->request->getArray('codes');
        if (false === is_array($codigos)) {
            return;
        }

        $contador = 0;
        $cliente = new Cliente();
        foreach ($codigos as $codigo) {
            if (false === $cliente->loadFromCode($codigo)) {
                continue;
            }

            $cliente->codgrupo = $this->request->query('code');
            if ($cliente->save()) {
                $contador++;
            }
        }

        Tools::log()->notice('items-added-correctly', ['%num%' => $contador]);
    }

    /**
     * Configura las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewCustomers();
        $this->createViewNewCustomers();
    }

    /**
     * Configura los elementos comunes de las vistas de clientes
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewCommon(string $viewName)
    {
        $this->views[$viewName]->addOrderBy(['codcliente'], 'code');
        $this->views[$viewName]->addOrderBy(['email'], 'email');
        $this->views[$viewName]->addOrderBy(['fechaalta'], 'creation-date');
        $this->views[$viewName]->addOrderBy(['nombre'], 'name', 1);
        $this->views[$viewName]->addOrderBy(['riesgoalcanzado'], 'current-risk');
        $this->views[$viewName]->searchFields = ['cifnif', 'codcliente', 'email', 'nombre', 'observaciones', 'razonsocial', 'telefono1', 'telefono2'];

        $this->views[$viewName]->disableColumn('group');
        $this->views[$viewName]->settings['btnNew'] = false;
        $this->views[$viewName]->settings['btnDelete'] = false;

        $idioma = Tools::lang();
        $valores = [
            ['label' => $idioma->trans('only-active'), 'where' => [new DataBaseWhere('debaja', false)]],
            ['label' => $idioma->trans('only-suspended'), 'where' => [new DataBaseWhere('debaja', true)]],
            ['label' => $idioma->trans('all'), 'where' => []]
        ];
        $this->views[$viewName]->addFilterSelectWhere('status', $valores);

        $this->views[$viewName]->addFilterSelect('codserie', 'series', 'codserie', Series::codeModel());
        $this->views[$viewName]->addFilterSelect('codretencion', 'retentions', 'codretencion', Retenciones::codeModel());
        $this->views[$viewName]->addFilterSelect('codpago', 'payment-methods', 'codpago', FormasPago::codeModel());
    }

    /**
     * Crea la vista de clientes del grupo
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewCustomers(string $viewName = 'ListCliente')
    {
        $this->addListView($viewName, 'Cliente', 'customers', 'fa-solid fa-users');
        $this->createViewCommon($viewName);

        $this->addButton($viewName, [
            'action' => 'remove-customer',
            'color' => 'danger',
            'confirm' => true,
            'icon' => 'fa-solid fa-folder-minus',
            'label' => 'remove-from-list'
        ]);
    }

    /**
     * Crea la vista de clientes sin grupo asignado
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewNewCustomers(string $viewName = 'ListCliente-new')
    {
        $this->addListView($viewName, 'Cliente', 'add', 'fa-solid fa-user-plus');
        $this->createViewCommon($viewName);

        $this->addButton($viewName, [
            'action' => 'add-customer',
            'color' => 'success',
            'icon' => 'fa-solid fa-folder-plus',
            'label' => 'add'
        ]);
    }

    /**
     * Ejecuta acciones previas
     * 
     * @param string $action Acción a ejecutar
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-customer':
                $this->addCustomerAction();
                return true;

            case 'remove-customer':
                $this->removeCustomerAction();
                return true;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Carga datos en una vista específica
     * 
     * @param string $viewName Nombre de la vista
     * @param BaseView $view Instancia de la vista
     */
    protected function loadData($viewName, $view)
    {
        $codgrupo = $this->getViewModelValue('EditGrupoClientes', 'codgrupo');
        switch ($viewName) {
            case 'ListCliente':
                $filtro = [new DataBaseWhere('codgrupo', $codgrupo)];
                $view->loadData('', $filtro);
                break;

            case 'ListCliente-new':
                $filtro = [new DataBaseWhere('codgrupo', null, 'IS')];
                $view->loadData('', $filtro);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Quita clientes seleccionados del grupo actual
     */
    protected function removeCustomerAction()
    {
        $codigos = $this->request->request->getArray('codes');
        if (false === is_array($codigos)) {
            return;
        }

        $contador = 0;
        $cliente = new Cliente();
        foreach ($codigos as $codigo) {
            if (false === $cliente->loadFromCode($codigo)) {
                continue;
            }

            $cliente->codgrupo = null;
            if ($cliente->save()) {
                $contador++;
            }
        }

        Tools::log()->notice('items-removed-correctly', ['%num%' => $contador]);
    }
}