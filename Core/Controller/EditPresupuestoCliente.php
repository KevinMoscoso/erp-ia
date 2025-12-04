<?php
/**
 * Copyright (C) 2024-2025 ERPIA Team
 */

namespace ERPIA\Controller;

use ERPIA\Core\Lib\AjaxForms\SalesController;

/**
 * Controlador para editar presupuestos de clientes
 * 
 * Gestiona los presupuestos de venta con funcionalidades extendidas de ventas.
 */
class EditPresupuestoCliente extends SalesController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'PresupuestoCliente';
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
        $pageData['title'] = 'estimation';
        $pageData['icon'] = 'fa-regular fa-file-powerpoint';
        $pageData['showonmenu'] = false;
        
        return $pageData;
    }
}