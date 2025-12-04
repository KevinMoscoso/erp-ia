<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de configuraciones del sistema
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Exercises;
use ERPIA\Core\DataSrc\Companies;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Lib\ExtendedController\EditView;
use ERPIA\Core\Lib\ExtendedController\PanelController;
use ERPIA\Core\Model\Settings;
use ERPIA\Core\Helpers;
use ERPIA\Core\Config;
use ERPIA\Dinamic\Model\Tax;

/**
 * Controlador para editar configuraciones principales del sistema
 */
class EditSettings extends PanelController
{
    const CONFIG_KEY = 'Settings';

    /**
     * Obtiene los datos de configuración de la página
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageInfo = parent::getPageData();
        $pageInfo['menu'] = 'administracion';
        $pageInfo['title'] = 'panel-control';
        $pageInfo['icon'] = 'fa-solid fa-tools';
        return $pageInfo;
    }

    /**
     * Verifica y ajusta el método de pago predeterminado
     *
     * @return bool
     */
    protected function validatePaymentMethod(): bool
    {
        $companyId = Config::get('default', 'idempresa');
        $filterCondition = [new DataBaseWhere('idempresa', $companyId)];
        $paymentOptions = $this->codeModel->getAll('formaspago', 'codpago', 'descripcion', false, $filterCondition);
        
        $currentPaymentMethod = Config::get('default', 'codpago');
        
        foreach ($paymentOptions as $option) {
            if ($option->code === $currentPaymentMethod) {
                return true;
            }
        }

        // Asignar un nuevo método de pago si hay opciones disponibles
        foreach ($paymentOptions as $option) {
            Config::set('default', 'codpago', $option->code);
            Config::save();
            return true;
        }

        // Desasignar el método de pago si no hay opciones
        Config::set('default', 'codpago', null);
        Config::save();
        return false;
    }

    /**
     * Verifica y ajusta el almacén predeterminado
     *
     * @return bool
     */
    protected function validateWarehouse(): bool
    {
        $companyId = Config::get('default', 'idempresa');
        $filterCondition = [new DataBaseWhere('idempresa', $companyId)];
        $warehouseOptions = $this->codeModel->getAll('almacenes', 'codalmacen', 'nombre', false, $filterCondition);
        
        $currentWarehouse = Config::get('default', 'codalmacen');
        
        foreach ($warehouseOptions as $option) {
            if ($option->code === $currentWarehouse) {
                return true;
            }
        }

        // Asignar un nuevo almacén si hay opciones disponibles
        foreach ($warehouseOptions as $option) {
            Config::set('default', 'codalmacen', $option->code);
            Config::save();
            return true;
        }

        // Desasignar el almacén si no hay opciones
        Config::set('default', 'codalmacen', null);
        Config::save();
        return false;
    }

    /**
     * Verifica y ajusta el impuesto predeterminado
     *
     * @return bool
     */
    protected function validateTax(): bool
    {
        $taxModel = new Tax();
        $currentTaxCode = Config::get('default', 'codimpuesto');
        
        if ($taxModel->load($currentTaxCode)) {
            return true;
        }

        // Desasignar el impuesto si no existe
        Config::set('default', 'codimpuesto', null);
        Config::save();
        return false;
    }

    /**
     * Crea filtro por tipo de documento
     *
     * @param string $viewName
     */
    protected function createDocumentTypeFilter(string $viewName): void
    {
        $documentTypes = $this->codeModel->getAll('estados_documentos', 'tipodoc', 'tipodoc');
        
        foreach ($documentTypes as $key => $type) {
            if (!empty($type->code)) {
                $type->description = Helpers::translate($type->code);
            }
        }
        
        $this->listView($viewName)->addFilterSelect('tipodoc', 'tipo-documento', 'tipodoc', $documentTypes);
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews(): void
    {
        $this->setTemplate('EditSettings');
        
        // Crear pestaña para cada archivo SettingsXXX
        $modelName = 'Settings';
        $defaultIcon = $this->getPageData()['icon'];
        $this->createSettingsView('SettingsDefault', $modelName, $defaultIcon);
        
        foreach ($this->getAllSettingsViews() as $viewName) {
            if ($viewName !== 'SettingsDefault') {
                $this->createSettingsView($viewName, $modelName, $defaultIcon);
            }
        }

        // Crear las demás pestañas
        $this->createApiKeysView();
        $this->createFiscalIdentifiersView();
        $this->createSequencesView();
        $this->createDocumentStatesView();
        $this->createDocumentFormatsView();
    }

    /**
     * Crea vista de claves API
     *
     * @param string $viewName
     */
    protected function createApiKeysView(string $viewName = 'ListApiKey'): void
    {
        $this->addListView($viewName, 'ApiKey', 'claves-api', 'fa-solid fa-key')
            ->addOrderBy(['id'], 'id')
            ->addOrderBy(['descripcion'], 'descripcion')
            ->addOrderBy(['fechacreacion', 'id'], 'fecha', 2)
            ->addSearchFields(['descripcion', 'apikey', 'nick']);
    }

    /**
     * Crea vista de identificadores fiscales
     *
     * @param string $viewName
     */
    protected function createFiscalIdentifiersView(string $viewName = 'EditIdentificadorFiscal'): void
    {
        $this->addEditListView($viewName, 'IdentificadorFiscal', 'identificadores-fiscales', 'fa-regular fa-id-card')
            ->enableInlineEditing(true);
    }

    /**
     * Crea vista de formatos de documento
     *
     * @param string $viewName
     */
    protected function createDocumentFormatsView(string $viewName = 'ListFormatoDocumento'): void
    {
        $this->addListView($viewName, 'FormatoDocumento', 'formatos-impresion', 'fa-solid fa-print')
            ->addOrderBy(['nombre'], 'nombre')
            ->addOrderBy(['titulo'], 'titulo')
            ->addSearchFields(['nombre', 'titulo', 'texto']);
            
        $this->createDocumentTypeFilter($viewName);
        
        $this->listView($viewName)
            ->addFilterSelect('idempresa', 'empresa', 'idempresa', Companies::codeModel())
            ->addFilterSelect('codserie', 'serie', 'codserie', Series::codeModel());
    }

    /**
     * Crea vista de configuración
     *
     * @param string $viewName
     * @param string $modelName
     * @param string $icon
     */
    protected function createSettingsView(string $viewName, string $modelName, string $icon): void
    {
        $viewTitle = $this->getViewKey($viewName);
        $this->addEditView($viewName, $modelName, $viewTitle, $icon);
        
        // Cambiar icono según el grupo
        $columnGroups = $this->views[$viewName]->getColumns();
        foreach ($columnGroups as $group) {
            if (!empty($group->icon)) {
                $this->views[$viewName]->icon = $group->icon;
                break;
            }
        }
        
        // Deshabilitar botones innecesarios
        $this->configureViewOption($viewName, 'btnDelete', false);
        $this->configureViewOption($viewName, 'btnNew', false);
    }

    /**
     * Crea vista de secuencias de documento
     *
     * @param string $viewName
     */
    protected function createSequencesView(string $viewName = 'ListSecuenciaDocumento'): void
    {
        $this->addListView($viewName, 'SecuenciaDocumento', 'secuencias', 'fa-solid fa-code')
            ->addOrderBy(['codejercicio', 'codserie', 'tipodoc'], 'ejercicio')
            ->addOrderBy(['codserie'], 'serie')
            ->addOrderBy(['numero'], 'numero')
            ->addOrderBy(['tipodoc', 'codejercicio', 'codserie'], 'tipo-documento', 1)
            ->addSearchFields(['patron', 'tipodoc']);
            
        // Deshabilitar columna de empresa si solo hay una
        if ($this->companyModel->totalCount() < 2) {
            $this->listView($viewName)->disableColumn('empresa');
        } else {
            $this->listView($viewName)->addFilterSelect('idempresa', 'empresa', 'idempresa', Companies::codeModel());
        }
        
        $this->listView($viewName)
            ->addFilterSelect('codejercicio', 'ejercicio', 'codejercicio', Exercises::codeModel())
            ->addFilterSelect('codserie', 'serie', 'codserie', Series::codeModel());
            
        $this->createDocumentTypeFilter($viewName);
    }

    /**
     * Crea vista de estados de documento
     *
     * @param string $viewName
     */
    protected function createDocumentStatesView(string $viewName = 'ListEstadoDocumento'): void
    {
        $this->addListView($viewName, 'EstadoDocumento', 'estados', 'fa-solid fa-tags')
            ->addOrderBy(['idestado'], 'id')
            ->addOrderBy(['nombre'], 'nombre')
            ->addSearchFields(['nombre']);
            
        $this->createDocumentTypeFilter($viewName);
        
        $this->listView($viewName)
            ->addFilterSelect('actualizastock', 'actualizar-stock', 'actualizastock', [
                ['code' => null, 'description' => '------'],
                ['code' => -2, 'description' => Helpers::translate('reservar')],
                ['code' => -1, 'description' => Helpers::translate('restar')],
                ['code' => 0, 'description' => Helpers::translate('no-hacer-nada')],
                ['code' => 1, 'description' => Helpers::translate('sumar')],
                ['code' => 2, 'description' => Helpers::translate('prever')],
            ])
            ->addFilterCheckbox('predeterminado', 'predeterminado', 'predeterminado')
            ->addFilterCheckbox('editable', 'editable', 'editable');
    }

    /**
     * Ejecuta acción de edición
     *
     * @return bool
     */
    protected function editAction(): bool
    {
        if (parent::editAction() === false) {
            return false;
        }
        
        Config::clearCache();
        
        // Verificar relaciones
        $this->validatePaymentMethod();
        $this->validateWarehouse();
        $this->validateTax();
        
        // Verificar URL del sitio
        $siteUrl = Config::get('default', 'site_url');
        if (empty($siteUrl)) {
            Config::set('default', 'site_url', Helpers::siteUrl());
            Config::save();
        }
        
        return true;
    }

    /**
     * Ejecuta acción de exportación
     */
    protected function exportAction(): void
    {
        // No hacer nada
    }

    /**
     * Carga datos en una vista específica
     *
     * @param string $viewName
     * @param EditView $view
     */
    protected function loadData(string $viewName, EditView $view): void
    {
        switch ($viewName) {
            case 'ListApiKey':
                $view->loadData();
                if (Config::get('default', 'enable_api', '0') === '0') {
                    $this->configureViewOption($viewName, 'active', false);
                }
                break;
                
            case 'EditIdentificadorFiscal':
                $view->loadData();
                break;
                
            case 'SettingsDefault':
                $configKey = $this->getViewKey($viewName);
                $view->loadData($configKey);
                
                if ($view->model instanceof Settings && empty($view->model->name)) {
                    $view->model->name = $configKey;
                }
                
                $this->loadPaymentMethodOptions($viewName);
                $this->loadWarehouseOptions($viewName);
                $this->loadLogoOptions($viewName);
                $this->loadSeriesOptions($viewName);
                $this->loadRectifyingSeriesOptions($viewName);
                break;
                
            default:
                $configKey = $this->getViewKey($viewName);
                $view->loadData($configKey);
                
                if ($view->model instanceof Settings && empty($view->model->name)) {
                    $view->model->name = $configKey;
                }
                break;
        }
    }

    /**
     * Carga opciones de imágenes para el logo
     *
     * @param string $viewName
     */
    protected function loadLogoOptions(string $viewName): void
    {
        $logoColumn = $this->views[$viewName]->getColumn('login-image');
        
        if ($logoColumn && $logoColumn->widget->getType() === 'select') {
            $imageFiles = $this->codeModel->getAll('attached_files', 'idfile', 'filename', true, [
                new DataBaseWhere('mimetype', 'image/gif,image/jpeg,image/png', 'IN')
            ]);
            
            $logoColumn->widget->setOptionsFromCodeModel($imageFiles);
        }
    }

    /**
     * Carga opciones de métodos de pago
     *
     * @param string $viewName
     */
    protected function loadPaymentMethodOptions(string $viewName): void
    {
        $companyId = Config::get('default', 'idempresa');
        $filterCondition = [new DataBaseWhere('idempresa', $companyId)];
        $paymentMethods = $this->codeModel->getAll('formaspago', 'codpago', 'descripcion', false, $filterCondition);
        
        $paymentColumn = $this->views[$viewName]->getColumn('payment-method');
        
        if ($paymentColumn && $paymentColumn->widget->getType() === 'select') {
            $paymentColumn->widget->setOptionsFromCodeModel($paymentMethods);
        }
    }

    /**
     * Carga opciones de series
     *
     * @param string $viewName
     */
    protected function loadSeriesOptions(string $viewName): void
    {
        $seriesColumn = $this->views[$viewName]->getColumn('serie');
        
        if ($seriesColumn && $seriesColumn->widget->getType() === 'select') {
            $seriesOptions = $this->codeModel->getAll('series', 'codserie', 'descripcion', false, [
                new DataBaseWhere('tipo', 'R', '!='),
                new DataBaseWhere('tipo', null, '=', 'OR')
            ]);
            
            $seriesColumn->widget->setOptionsFromCodeModel($seriesOptions);
        }
    }

    /**
     * Carga opciones de series rectificativas
     *
     * @param string $viewName
     */
    protected function loadRectifyingSeriesOptions(string $viewName): void
    {
        $rectifyingColumn = $this->views[$viewName]->getColumn('rectifying-serie');
        
        if ($rectifyingColumn && $rectifyingColumn->widget->getType() === 'select') {
            $rectifyingSeries = $this->codeModel->getAll('series', 'codserie', 'descripcion', false, [
                new DataBaseWhere('tipo', 'R')
            ]);
            
            $rectifyingColumn->widget->setOptionsFromCodeModel($rectifyingSeries);
        }
    }

    /**
     * Carga opciones de almacenes
     *
     * @param string $viewName
     */
    protected function loadWarehouseOptions(string $viewName): void
    {
        $companyId = Config::get('default', 'idempresa');
        $filterCondition = [new DataBaseWhere('idempresa', $companyId)];
        $warehouses = $this->codeModel->getAll('almacenes', 'codalmacen', 'nombre', false, $filterCondition);
        
        $warehouseColumn = $this->views[$viewName]->getColumn('warehouse');
        
        if ($warehouseColumn && $warehouseColumn->widget->getType() === 'select') {
            $warehouseColumn->widget->setOptionsFromCodeModel($warehouses);
        }
    }

    /**
     * Obtiene todas las vistas de configuración XML
     *
     * @return array
     */
    private function getAllSettingsViews(): array
    {
        $viewNames = [];
        $viewsPath = ERPIA_FOLDER . '/Dinamic/XMLView';
        
        foreach (Helpers::scanDirectory($viewsPath) as $fileName) {
            if (strpos($fileName, self::CONFIG_KEY) === 0) {
                $viewNames[] = substr($fileName, 0, -4);
            }
        }
        
        return $viewNames;
    }

    /**
     * Obtiene la clave de configuración a partir del nombre de la vista
     *
     * @param string $viewName
     * @return string
     */
    private function getViewKey(string $viewName): string
    {
        return strtolower(substr($viewName, strlen(self::CONFIG_KEY)));
    }
}