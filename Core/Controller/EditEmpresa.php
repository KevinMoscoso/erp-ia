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
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\RegimenIVA;

/**
 * Controlador para editar un elemento individual del modelo Empresa
 * 
 * Gestiona la información de la empresa incluyendo almacenes, cuentas bancarias,
 * métodos de pago y ejercicios, con verificación VIES y configuración de IVA.
 */
class EditEmpresa extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Empresa';
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
        $pageData['title'] = 'company';
        $pageData['icon'] = 'fa-solid fa-building';
        
        return $pageData;
    }

    /**
     * Ejecuta la verificación del número de IVA en VIES
     * 
     * @return bool
     */
    protected function checkViesAction(): bool
    {
        $modelo = $this->getModel();
        $codigo = $this->request->input('code');
        if (false === $modelo->loadFromCode($codigo)) {
            return true;
        }

        if ($modelo->verificarVies()) {
            Tools::log()->notice('vies-check-success', ['%vat-number%' => $modelo->cifnif]);
        }

        return true;
    }

    /**
     * Configura las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();

        $this->createViewWarehouse();
        $this->createViewBankAccounts();
        $this->createViewPaymentMethods();
        $this->createViewExercises();
    }

    /**
     * Crea la vista de cuentas bancarias
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewBankAccounts(string $viewName = 'ListCuentaBanco'): void
    {
        $this->addListView($viewName, 'CuentaBanco', 'bank-accounts', 'fa-solid fa-piggy-bank')
            ->disableColumn('company');
    }

    /**
     * Crea la vista de ejercicios
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewExercises(string $viewName = 'ListEjercicio'): void
    {
        $this->addListView($viewName, 'Ejercicio', 'exercises', 'fa-solid fa-calendar-alt')
            ->disableColumn('company');
    }

    /**
     * Crea la vista de métodos de pago
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewPaymentMethods(string $viewName = 'ListFormaPago'): void
    {
        $this->addListView($viewName, 'FormaPago', 'payment-method', 'fa-solid fa-credit-card')
            ->disableColumn('company');
    }

    /**
     * Crea la vista de almacenes
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewWarehouse(string $viewName = 'EditAlmacen'): void
    {
        $this->addListView($viewName, 'Almacen', 'warehouses', 'fa-solid fa-warehouse')
            ->disableColumn('company');
    }

    /**
     * Ejecuta acciones previas
     * 
     * @param string $action Acción a ejecutar
     * @return bool
     */
    protected function execPreviousAction($action): bool
    {
        switch ($action) {
            case 'check-vies':
                return $this->checkViesAction();

            default:
                return parent::execPreviousAction($action);
        }
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
            case 'EditAlmacen':
            case 'ListCuentaBanco':
            case 'ListEjercicio':
            case 'ListFormaPago':
                $idEmpresa = $this->getViewModelValue($vistaPrincipal, 'idempresa');
                $filtro = [new DataBaseWhere('idempresa', $idEmpresa)];
                $view->loadData('', $filtro);
                break;

            case $vistaPrincipal:
                parent::loadData($viewName, $view);
                $this->setCustomWidgetValues($view);
                if ($view->model->exists() && $view->model->cifnif) {
                    $this->addButton($viewName, [
                        'action' => 'check-vies',
                        'color' => 'info',
                        'icon' => 'fa-solid fa-check-double',
                        'label' => 'check-vies'
                    ]);
                }
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Configura los valores personalizados de los widgets
     * 
     * @param BaseView $view Vista principal
     */
    protected function setCustomWidgetValues(BaseView &$view): void
    {
        $columnaTipoIVA = $view->columnForName('vat-regime');
        if ($columnaTipoIVA && $columnaTipoIVA->widget->getType() === 'select') {
            $columnaTipoIVA->widget->setValuesFromArrayKeys(RegimenIVA::todos(), true);
        }

        $columnaExcepcionIVA = $view->columnForName('vat-exception');
        if ($columnaExcepcionIVA && $columnaExcepcionIVA->widget->getType() === 'select') {
            $columnaExcepcionIVA->widget->setValuesFromArrayKeys(RegimenIVA::todasExcepciones(), true, true);
        }
    }
}