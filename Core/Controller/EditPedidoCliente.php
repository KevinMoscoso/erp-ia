<?php
/**
 * Copyright (C) 2024-2025 ERPIA Team
 */

namespace ERPIA\Controller;

use ERPIA\Core\Lib\AjaxForms\SalesController;

/**
 * Controlador para editar pedidos de clientes
 * 
 * Gestiona los pedidos de venta con funcionalidades extendidas de ventas.
 */
class EditPedidoCliente extends SalesController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'PedidoCliente';
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
        $pageData['title'] = 'order';
        $pageData['icon'] = 'fa-solid fa-file-powerpoint';
        $pageData['showonmenu'] = false;
        
        return $pageData;
    }
}