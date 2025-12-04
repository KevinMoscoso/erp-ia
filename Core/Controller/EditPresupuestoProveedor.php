<?php
/**
 * Copyright (C) 2024-2025 ERPIA Team
 */

namespace ERPIA\Controller;

use ERPIA\Core\Lib\AjaxForms\PurchasesController;

/**
 * Controlador para editar presupuestos de proveedores
 * 
 * Gestiona los presupuestos de compra con funcionalidades extendidas de compras.
 */
class EditPresupuestoProveedor extends PurchasesController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'PresupuestoProveedor';
    }

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'purchases';
        $pageData['title'] = 'estimation';
        $pageData['icon'] = 'fa-regular fa-file-powerpoint';
        $pageData['showonmenu'] = false;
        
        return $pageData;
    }
}