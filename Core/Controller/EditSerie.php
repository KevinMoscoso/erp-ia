<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de series
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Exercises;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\Helpers;

/**
 * Controlador para la edición de un registro del modelo Serie
 */
class EditSerie extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Serie';
    }

    /**
     * Obtiene los datos de configuración de la página
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageInfo = parent::getPageData();
        $pageInfo['menu'] = 'contabilidad';
        $pageInfo['title'] = 'serie';
        $pageInfo['icon'] = 'fa-solid fa-layer-group';
        return $pageInfo;
    }

    /**
     * Crea la vista de formatos de documento
     *
     * @param string $viewName
     */
    protected function createFormatView(string $viewName = 'ListFormatoDocumento'): void
    {
        $this->addListView($viewName, 'FormatoDocumento', 'formatos-impresion', 'fa-solid fa-print');
        $this->views[$viewName]->addOrderBy(['tipodoc'], 'tipo-documento', 2);

        // Desactivar columna de serie
        $this->views[$viewName]->disableColumn('serie');
    }

    /**
     * Crea la vista de secuencias de documento
     *
     * @param string $viewName
     */
    protected function createSequenceView(string $viewName = 'ListSecuenciaDocumento'): void
    {
        $this->addListView($viewName, 'SecuenciaDocumento', 'secuencias', 'fa-solid fa-code')
            ->addOrderBy(['codejercicio', 'tipodoc'], 'ejercicio')
            ->addOrderBy(['tipodoc', 'codejercicio'], 'tipo-documento', 1)
            ->addSearchFields(['patron', 'tipodoc'])
            ->disableColumn('serie');

        // Desactivar columna de empresa si solo hay una
        if ($this->empresaModel->totalCount() < 2) {
            $this->listView($viewName)->disableColumn('empresa');
        }

        // Filtros
        $documentTypes = $this->codeModel->all('estados_documentos', 'tipodoc', 'tipodoc');
        foreach ($documentTypes as $type) {
            if (!empty($type->code)) {
                $type->description = Helpers::translate($type->code);
            }
        }

        $this->listView($viewName)
            ->addFilterSelect('tipodoc', 'tipo-documento', 'tipodoc', $documentTypes)
            ->addFilterSelect('codejercicio', 'ejercicio', 'codejercicio', Exercises::codeModel());
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews(): void
    {
        parent::createViews();
        $this->configureTabPosition('bottom');

        $this->createSequenceView();
        $this->createFormatView();
    }

    /**
     * Carga datos en una vista específica
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData(string $viewName, BaseView $view): void
    {
        switch ($viewName) {
            case 'ListFormatoDocumento':
            case 'ListSecuenciaDocumento':
                $serieCode = $this->getViewModelValue($this->getMainViewName(), 'codserie');
                $filterCondition = [new DataBaseWhere('codserie', $serieCode)];
                $view->loadData('', $filterCondition);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}