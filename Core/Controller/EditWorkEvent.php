<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de eventos de trabajo
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controlador para la edición de registros de eventos de trabajo
 */
class EditWorkEvent extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'WorkEvent';
    }

    /**
     * Obtiene los datos de configuración de la página
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageInfo = parent::getPageData();
        $pageInfo['menu'] = 'administracion';
        $pageInfo['title'] = 'WorkEvent';
        $pageInfo['icon'] = 'fa-solid fa-search';
        return $pageInfo;
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews(): void
    {
        parent::createViews();
        $this->configureTabPosition('bottom');

        // Desactivar botón de nuevo
        $mainView = $this->getMainViewName();
        $this->configureViewOption($mainView, 'btnNew', false);
    }
}