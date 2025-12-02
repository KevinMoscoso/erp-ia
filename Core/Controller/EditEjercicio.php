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
use ERPIA\Core\Lib\Accounting\ClosingToAcounting;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\Accounting\AccountingPlanExport;
use ERPIA\Dinamic\Lib\Accounting\AccountingPlanImport;
use ERPIA\Dinamic\Model\Ejercicio;

/**
 * Controlador para editar un elemento individual del modelo Ejercicio
 * 
 * Gestiona ejercicios contables incluyendo cierre, apertura, importación
 * y exportación de planes contables, así como la visualización de cuentas
 * y asientos asociados.
 */
class EditEjercicio extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Ejercicio';
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
        $pageData['title'] = 'exercise';
        $pageData['icon'] = 'fa-solid fa-calendar-alt';
        
        return $pageData;
    }

    /**
     * Agrega botones de acción según el estado del ejercicio
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function addExerciseActionButtons(string $viewName): void
    {
        $estado = $this->getViewModelValue($viewName, 'estado');
        $modelEjercicio = new Ejercicio();
        
        switch ($estado) {
            case $modelEjercicio::ESTADO_ABIERTO:
                $this->addButton($viewName, [
                    'row' => 'footer-actions',
                    'action' => 'import-accounting',
                    'color' => 'warning',
                    'icon' => 'fa-solid fa-file-import',
                    'label' => 'import-accounting-plan',
                    'type' => 'modal'
                ]);

                $this->addButton($viewName, [
                    'row' => 'footer-actions',
                    'action' => 'close-exercise',
                    'color' => 'danger',
                    'icon' => 'fa-solid fa-calendar-check',
                    'label' => 'close-exercise',
                    'type' => 'modal'
                ]);
                break;

            case $modelEjercicio::ESTADO_CERRADO:
                $this->addButton($viewName, [
                    'row' => 'footer-actions',
                    'action' => 'open-exercise',
                    'color' => 'warning',
                    'icon' => 'fa-solid fa-calendar-plus',
                    'label' => 'open-exercise',
                    'type' => 'modal'
                ]);
                break;
        }
    }

    /**
     * Verifica permisos y carga el ejercicio
     * 
     * @param string $code Código del ejercicio
     * @return bool
     */
    private function checkAndLoad(string $code): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('no-permission-modify');
            return false;
        }

        if (false === $this->getModel()->loadFromCode($code)) {
            Tools::log()->error('record-not-found');
            return false;
        }

        return true;
    }

    /**
     * Ejecuta el cierre del ejercicio contable
     * 
     * @return bool
     */
    protected function closeExerciseAction(): bool
    {
        $codigo = $this->request->input('codejercicio');
        if (false === $this->checkAndLoad($codigo)) {
            return true;
        }

        $datos = [
            'journalClosing' => $this->request->input('iddiario-closing'),
            'journalOpening' => $this->request->input('iddiario-opening'),
            'copySubAccounts' => (bool)$this->request->input('copysubaccounts', false)
        ];

        $modelo = $this->getModel();
        $cierre = new ClosingToAcounting();
        if ($cierre->ejecutar($modelo, $datos)) {
            Tools::log()->notice('accounting-closing-completed');
        }
        // No se requiere mensaje de error
        return true;
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();

        // Deshabilitar columna de empresa si solo hay una
        if ($this->empresa->count() < 2) {
            $this->views[$this->getMainViewName()]->disableColumn('company');
        }

        $this->createViewsAccounting();
        $this->createViewsSubaccounting();
        $this->createViewsAccountingEntries();
    }

    /**
     * Crea la vista de cuentas contables
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsAccounting(string $viewName = 'ListCuenta'): void
    {
        $this->addListView($viewName, 'Cuenta', 'accounts', 'fa-solid fa-book')
            ->addOrderBy(['codcuenta'], 'code', 1)
            ->addSearchFields(['codcuenta', 'descripcion'])
            ->disableColumn('fiscal-exercise')
            ->disableColumn('parent-account');
    }

    /**
     * Crea la vista de asientos contables especiales
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsAccountingEntries(string $viewName = 'ListAsiento'): void
    {
        $this->addListView($viewName, 'Asiento', 'special-accounting-entries', 'fa-solid fa-balance-scale')
            ->addOrderBy(['fecha', 'numero'], 'date')
            ->addSearchFields(['concepto', 'numero'])
            ->disableColumn('exercise')
            ->setSettings('btnNew', false);
    }

    /**
     * Crea la vista de subcuentas
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsSubaccounting(string $viewName = 'ListSubcuenta'): void
    {
        $this->addListView($viewName, 'Subcuenta', 'subaccounts')
            ->addOrderBy(['codsubcuenta'], 'code', 1)
            ->addOrderBy(['saldo'], 'balance')
            ->addSearchFields(['codsubcuenta', 'descripcion'])
            ->disableColumn('fiscal-exercise');
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
            case 'close-exercise':
                return $this->closeExerciseAction();

            case 'export-accounting':
                return $this->exportAccountingPlan();

            case 'import-accounting':
                return $this->importAccountingPlan();

            case 'open-exercise':
                return $this->openExerciseAction();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Exporta el plan contable a archivo CSV
     * 
     * @return bool
     */
    protected function exportAccountingPlan(): bool
    {
        if (false === $this->permissions->allowImport) {
            Tools::log()->warning('no-export-permission');
            return true;
        }

        $codejercicio = $this->request->queryOrInput('code', '');
        if (empty($codejercicio)) {
            Tools::log()->error('exercise-not-found');
            return true;
        }

        $this->setTemplate(false);

        $exportador = new AccountingPlanExport();

        $this->response
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment;filename=' . $codejercicio . '.csv')
            ->setContent($exportador->exportarCSV($codejercicio));
        return false;
    }

    /**
     * Importa un plan contable desde archivo
     * 
     * @return bool
     */
    protected function importAccountingPlan(): bool
    {
        if (false === $this->permissions->allowImport) {
            Tools::log()->warning('no-import-permission');
            return true;
        }

        $codejercicio = $this->request->input('codejercicio', '');
        if (empty($codejercicio)) {
            Tools::log()->error('exercise-not-found');
            return true;
        }

        $archivoSubido = $this->request->files->get('accountingfile');
        if (empty($archivoSubido)) {
            return $this->importDefaultPlan($codejercicio);
        }

        $importador = new AccountingPlanImport();
        switch ($archivoSubido->getMimeType()) {
            case 'application/xml':
            case 'text/xml':
                if ($importador->importarXML($archivoSubido->getPathname(), $codejercicio)) {
                    Tools::log()->notice('record-updated-correctly');
                    return true;
                }
                Tools::log()->error('record-save-error');
                return true;

            case 'text/csv':
            case 'text/plain':
                if ($importador->importarCSV($archivoSubido->getPathname(), $codejercicio)) {
                    Tools::log()->notice('record-updated-correctly');
                    return true;
                }
                Tools::log()->error('record-save-error');
                return true;
        }

        Tools::log()->error('file-not-supported');
        return true;
    }

    /**
     * Importa el plan contable por defecto
     * 
     * @param string $codejercicio Código del ejercicio
     * @return bool
     */
    protected function importDefaultPlan(string $codejercicio): bool
    {
        $rutaArchivo = ERPIA_FOLDER . '/Dinamic/Data/Lang/' . Tools::config('lang') . '/defaultPlan.csv';
        if (false === file_exists($rutaArchivo)) {
            $codpais = Tools::settings('default', 'codpais');
            $rutaArchivo = ERPIA_FOLDER . '/Dinamic/Data/Codpais/' . $codpais . '/defaultPlan.csv';
        }

        if (false === file_exists($rutaArchivo)) {
            Tools::log()->warning('file-not-found', ['%fileName%' => $rutaArchivo]);
            return true;
        }

        $importador = new AccountingPlanImport();
        if ($importador->importarCSV($rutaArchivo, $codejercicio)) {
            Tools::log()->notice('record-updated-correctly');
            return true;
        }

        Tools::log()->error('record-save-error');
        return true;
    }

    /**
     * Carga datos en una vista específica
     * 
     * @param string $viewName Nombre de la vista
     * @param BaseView $view Instancia de la vista
     */
    protected function loadData($viewName, $view)
    {
        $codejercicio = $this->getViewModelValue('EditEjercicio', 'codejercicio');

        switch ($viewName) {
            case 'EditEjercicio':
                parent::loadData($viewName, $view);
                $this->addExerciseActionButtons($viewName);
                break;

            case 'ListAsiento':
                $filtros = [
                    new DataBaseWhere('codejercicio', $codejercicio),
                    new DataBaseWhere('operacion', null, 'IS NOT')
                ];
                $view->loadData('', $filtros);
                break;

            case 'ListCuenta':
            case 'ListSubcuenta':
                $filtros = [new DataBaseWhere('codejercicio', $codejercicio)];
                $view->loadData('', $filtros);

                // Ocultar columna saldo de los totales
                unset($view->totalAmounts['saldo']);
                break;
        }
    }

    /**
     * Reabre un ejercicio cerrado
     * 
     * @return bool
     */
    protected function openExerciseAction(): bool
    {
        $codigo = $this->request->input('codejercicio');
        if (false === $this->checkAndLoad($codigo)) {
            return true;
        }
        $datos = [
            'deleteClosing' => (bool)$this->request->input('delete-closing'),
            'deleteOpening' => (bool)$this->request->input('delete-opening')
        ];
        $modelo = $this->getModel();
        $cierre = new ClosingToAcounting();
        if ($cierre->eliminar($modelo, $datos)) {
            Tools::log()->notice('accounting-opening-completed');
        }
        // No se requiere mensaje de error
        return true;
    }
}