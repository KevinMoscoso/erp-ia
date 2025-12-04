<?php
/**
 * ERPIA - Sistema de Gestión Empresarial
 * Este archivo es parte de ERPIA, software libre bajo licencia GPL.
 * 
 * @package    ERPIA\Core\Controller
 * @author     Equipo de Desarrollo ERPIA
 * @copyright  2023-2025 ERPIA
 * @license    GNU Lesser General Public License v3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\DataSrc\Almacenes;
use ERPIA\Core\Lib\ExtendedController\ListController;
use ERPIA\Dinamic\Model\LineaFacturaCliente;
use ERPIA\Dinamic\Model\LineaFacturaProveedor;

/**
 * Informe de productos con estadísticas de ventas y compras
 * 
 * Muestra datos agregados de productos en albaranes y facturas
 * de clientes y proveedores con múltiples filtros y ordenaciones.
 */
class ReportProducto extends ListController
{
    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título e icono
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'reports';
        $pageData['title'] = 'products';
        $pageData['icon'] = 'fa-solid fa-cubes';
        
        return $pageData;
    }

    /**
     * Crea las vistas del controlador
     * 
     * Inicializa las cuatro vistas de informe de productos
     */
    protected function createViews(): void
    {
        // Asegurar dependencias de modelos
        new LineaFacturaCliente();
        new LineaFacturaProveedor();

        $this->createViewsSupplierDeliveryNotes();
        $this->createViewsSupplierInvoices();
        $this->createViewsCustomerDeliveryNotes();
        $this->createViewsCustomerInvoices();
    }

    /**
     * Crea la vista de albaranes de proveedores
     * 
     * @param string $viewName Nombre de la vista (por defecto: FacturaProveedorProducto-alb)
     */
    protected function createViewsSupplierDeliveryNotes(string $viewName = 'FacturaProveedorProducto-alb'): void
    {
        $this->addView($viewName, 'Join\AlbaranProveedorProducto', 'supplier-delivery-notes', 'fa-solid fa-copy')
            ->addOrderBy(['cantidad'], 'purchased-quantity', 2)
            ->addOrderBy(['avgcoste'], 'unit-purchase-price')
            ->addOrderBy(['coste'], 'cost-price')
            ->addOrderBy(['precio'], 'price')
            ->addOrderBy(['stockfis'], 'stock')
            ->addSearchFields(['productos.descripcion', 'lineasalbaranesprov.referencia']);

        // Filtros
        $this->addCommonFilters($viewName, 'albaranesprov.fecha');
        $this->addFilterAutocomplete($viewName, 'codproveedor', 'supplier', 'codproveedor', 'Proveedor', 'codproveedor', 'nombre');

        // Desactivar botones
        $this->disableButtons($viewName);
    }

    /**
     * Crea la vista de facturas de proveedores
     * 
     * @param string $viewName Nombre de la vista (por defecto: FacturaProveedorProducto)
     */
    protected function createViewsSupplierInvoices(string $viewName = 'FacturaProveedorProducto'): void
    {
        $this->addView($viewName, 'Join\FacturaProveedorProducto', 'supplier-invoices', 'fa-solid fa-copy')
            ->addOrderBy(['cantidad'], 'quantity', 2)
            ->addSearchFields(['productos.descripcion', 'lineasfacturasprov.referencia']);

        // Filtros
        $this->addCommonFilters($viewName, 'facturasprov.fecha');
        $this->addFilterAutocomplete($viewName, 'codproveedor', 'supplier', 'codproveedor', 'Proveedor', 'codproveedor', 'nombre');

        // Desactivar botones
        $this->disableButtons($viewName);
    }

    /**
     * Crea la vista de albaranes de clientes
     * 
     * @param string $viewName Nombre de la vista (por defecto: FacturaClienteProducto-alb)
     */
    protected function createViewsCustomerDeliveryNotes(string $viewName = 'FacturaClienteProducto-alb'): void
    {
        $this->addView($viewName, 'Join\AlbaranClienteProducto', 'customer-delivery-notes', 'fa-solid fa-shipping-fast')
            ->addOrderBy(['cantidad'], 'quantity-sold', 2)
            ->addOrderBy(['avgbeneficio'], 'unit-profit')
            ->addOrderBy(['avgprecio'], 'unit-sale-price')
            ->addOrderBy(['coste'], 'cost-price')
            ->addOrderBy(['precio'], 'price')
            ->addOrderBy(['stockfis'], 'stock')
            ->addSearchFields(['productos.descripcion', 'lineasalbaranescli.referencia']);

        // Filtros
        $this->addCommonFilters($viewName, 'albaranescli.fecha');
        $this->addFilterAutocomplete($viewName, 'codcliente', 'customer', 'codcliente', 'Cliente', 'codcliente', 'nombre');

        // Desactivar botones
        $this->disableButtons($viewName);
    }

    /**
     * Crea la vista de facturas de clientes
     * 
     * @param string $viewName Nombre de la vista (por defecto: FacturaClienteProducto)
     */
    protected function createViewsCustomerInvoices(string $viewName = 'FacturaClienteProducto'): void
    {
        $this->addView($viewName, 'Join\FacturaClienteProducto', 'customer-invoices', 'fa-solid fa-shopping-cart')
            ->addOrderBy(['cantidad'], 'quantity-sold', 2)
            ->addOrderBy(['avgbeneficio'], 'unit-profit')
            ->addOrderBy(['avgprecio'], 'unit-sale-price')
            ->addOrderBy(['coste'], 'cost-price')
            ->addOrderBy(['precio'], 'price')
            ->addOrderBy(['stockfis'], 'stock')
            ->addSearchFields(['productos.descripcion', 'lineasfacturascli.referencia']);

        // Filtros
        $this->addCommonFilters($viewName, 'facturascli.fecha');
        $this->addFilterAutocomplete($viewName, 'codcliente', 'customer', 'codcliente', 'Cliente', 'codcliente', 'nombre');

        // Desactivar botones
        $this->disableButtons($viewName);
    }

    /**
     * Añade filtros comunes a una vista
     * 
     * @param string $viewName Nombre de la vista
     * @param string $dateField Campo de fecha para el filtro de período
     */
    private function addCommonFilters(string $viewName, string $dateField): void
    {
        $this->addFilterPeriod($viewName, 'fecha', 'date', $dateField);

        $warehouses = Almacenes::codeModel();
        if (count($warehouses) > 2) {
            $this->addFilterSelect($viewName, 'codalmacen', 'warehouse', 'codalmacen', $warehouses);
        } else {
            $this->views[$viewName]->disableColumn('warehouse');
        }

        $manufacturers = $this->codeModel->all('fabricantes', 'codfabricante', 'nombre');
        $this->addFilterSelect($viewName, 'codfabricante', 'manufacturer', 'codfabricante', $manufacturers);

        $families = $this->codeModel->all('familias', 'codfamilia', 'descripcion');
        $this->addFilterSelect($viewName, 'codfamilia', 'family', 'codfamilia', $families);
    }

    /**
     * Desactiva botones y funcionalidades de edición en una vista
     * 
     * @param string $viewName Nombre de la vista
     */
    private function disableButtons(string $viewName): void
    {
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'checkBoxes', false);
        $this->setSettings($viewName, 'clickable', false);
    }
}