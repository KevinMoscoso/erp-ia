<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de puntos de interés en ciudades
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controlador para la edición de registros de puntos de interés en ciudades
 */
class EditPuntoInteresCiudad extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'PuntoInteresCiudad';
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
        $pageInfo['title'] = 'PuntoInteresCiudad';
        $pageInfo['icon'] = 'fa-solid fa-search';
        return $pageInfo;
    }
}