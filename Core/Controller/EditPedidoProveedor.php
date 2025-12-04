<?php
/**
 * Copyright (C) 2024-2025 ERPIA Team
 */

namespace ERPIA\Controller;

use ERPIA\Core\Lib\AjaxForms\PurchasesController;

/**
 * Controlador para editar pedidos de proveedores
 * 
 * Gestiona los pedidos de compra con funcionalidades extendidas de compras.
 */
class EditPedidoProveedor extends PurchasesController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'PedidoProveedor';
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
        $pageData['title'] = 'order';
        $pageData['icon'] = 'fa-solid fa-file-powerpoint';
        $pageData['showonmenu'] = false;
        
        return $pageData;
    }
}