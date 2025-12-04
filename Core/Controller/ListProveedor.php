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

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\FormasPago;
use ERPIA\Core\DataSrc\Paises;
use ERPIA\Core\DataSrc\Retenciones;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Lib\ExtendedController\ListController;
use ERPIA\Core\Translator;

/**
 * Controlador para listar los elementos del modelo Proveedor
 * 
 * Gestiona la visualización de proveedores y sus contactos/direcciones
 * con filtros avanzados para la gestión de compras.
 */
class ListProveedor extends ListController
{
    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título e icono
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'purchases';
        $pageData['title'] = 'suppliers';
        $pageData['icon'] = 'fa-solid fa-users';
        
        return $pageData;
    }

    /**
     * Crea las vistas del controlador
     * 
     * Inicializa las vistas de proveedores y contactos/direcciones
     */
    protected function createViews(): void
    {
        $this->createViewSuppliers();
        $this->createViewAddresses();
    }

    /**
     * Crea la vista de contactos y direcciones
     * 
     * @param string $viewName Nombre de la vista (por defecto: ListContacto)
     */
    protected function createViewAddresses(string $viewName = 'ListContacto'): void
    {
        $this->addView($viewName, 'Contacto', 'addresses-and-contacts', 'fa-solid fa-address-book')
            ->addOrderBy(['descripcion'], 'description')
            ->addOrderBy(['direccion'], 'address')
            ->addOrderBy(['nombre'], 'name')
            ->addOrderBy(['fechaalta'], 'creation-date', 2)
            ->addSearchFields([
                'apartado', 'apellidos', 'codpostal', 'descripcion', 'direccion', 'email', 'empresa',
                'nombre', 'observaciones', 'telefono1', 'telefono2'
            ]);

        $translator = Translator::getInstance();

        // Filtro de tipo (proveedores vs todos)
        $values = [
            [
                'label' => $translator->trans('suppliers'),
                'where' => [new DataBaseWhere('codproveedor', null, 'IS NOT')]
            ],
            [
                'label' => $translator->trans('all'),
                'where' => []
            ]
        ];
        $this->addFilterSelectWhere($viewName, 'type', $values);

        // Filtro de cargo
        $cargoValues = $this->codeModel->all('contactos', 'cargo', 'cargo');
        $this->addFilterSelect($viewName, 'cargo', 'position', 'cargo', $cargoValues);

        // Filtro de país
        $this->addFilterSelect($viewName, 'codpais', 'country', 'codpais', Paises::codeModel());

        // Filtro de provincia
        $provinces = $this->codeModel->all('contactos', 'provincia', 'provincia');
        $this->addFilterSelect($viewName, 'provincia', 'province', 'provincia', $provinces);

        // Filtro de ciudad
        $cities = $this->codeModel->all('contactos', 'ciudad', 'ciudad');
        $this->addFilterSelect($viewName, 'ciudad', 'city', 'ciudad', $cities);

        // Filtro de código postal (autocompletado)
        $this->addFilterAutocomplete($viewName, 'codpostal', 'zip-code', 'codpostal', 'contactos', 'codpostal');

        // Filtro de verificación
        $this->addFilterCheckbox($viewName, 'verificado', 'verified', 'verificado');

        // Desactivar mega-search
        $this->setSettings($viewName, 'megasearch', false);
    }

    /**
     * Crea la vista principal de proveedores
     * 
     * @param string $viewName Nombre de la vista (por defecto: ListProveedor)
     */
    protected function createViewSuppliers(string $viewName = 'ListProveedor'): void
    {
        $this->addView($viewName, 'Proveedor', 'suppliers', 'fa-solid fa-users')
            ->addOrderBy(['codproveedor'], 'code')
            ->addOrderBy(['cifnif'], 'fiscal-number')
            ->addOrderBy(['LOWER(nombre)'], 'name', 1)
            ->addOrderBy(['fechaalta'], 'creation-date')
            ->addSearchFields([
                'cifnif', 'codproveedor', 'codsubcuenta', 'email', 'nombre', 'observaciones', 'razonsocial',
                'telefono1', 'telefono2'
            ]);

        $translator = Translator::getInstance();

        // Filtros de estado
        $this->addFilterSelectWhere($viewName, 'status', [
            ['label' => $translator->trans('only-active'), 'where' => [new DataBaseWhere('debaja', false)]],
            ['label' => $translator->trans('only-suspended'), 'where' => [new DataBaseWhere('debaja', true)]],
            ['label' => $translator->trans('all'), 'where' => []]
        ]);

        // Filtros de tipo (acreedor vs proveedor)
        $this->addFilterSelectWhere($viewName, 'type', [
            ['label' => $translator->trans('all'), 'where' => []],
            ['label' => $translator->trans('is-creditor'), 'where' => [new DataBaseWhere('acreedor', true)]],
            ['label' => $translator->trans('supplier'), 'where' => [new DataBaseWhere('acreedor', false)]],
        ]);

        // Filtro de tipo de identificación fiscal
        $fiscalIds = $this->codeModel->all('proveedores', 'tipoidfiscal', 'tipoidfiscal');
        $this->addFilterSelect($viewName, 'tipoidfiscal', 'fiscal-id', 'tipoidfiscal', $fiscalIds);

        // Filtros de configuración empresarial
        $this->addFilterSelect($viewName, 'codserie', 'series', 'codserie', Series::codeModel());
        $this->addFilterSelect($viewName, 'codretencion', 'retentions', 'codretencion', Retenciones::codeModel());
        $this->addFilterSelect($viewName, 'codpago', 'payment-methods', 'codpago', FormasPago::codeModel());

        // Filtro de régimen de IVA
        $vatRegimes = $this->codeModel->all('proveedores', 'regimeniva', 'regimeniva');
        $this->addFilterSelect($viewName, 'regimeniva', 'vat-regime', 'regimeniva', $vatRegimes);
    }
}