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
use ERPIA\Core\DataSrc\Almacenes;
use ERPIA\Core\DataSrc\Impuestos;
use ERPIA\Core\Lib\ExtendedController\ListController;
use ERPIA\Core\Lib\ProductType;
use ERPIA\Core\Model\CodeModel;
use ERPIA\Core\Translator;
use ERPIA\Dinamic\Lib\RegimenIVA;
use ERPIA\Dinamic\Model\Atributo;

/**
 * Controlador para listar los elementos del modelo Producto
 * 
 * Gestiona la visualización de productos, variantes y stock
 * con múltiples vistas y filtros avanzados para la gestión de inventario.
 */
class ListProducto extends ListController
{
    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título e icono
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'products';
        $pageData['icon'] = 'fa-solid fa-cubes';
        
        return $pageData;
    }

    /**
     * Crea las vistas del controlador
     * 
     * Inicializa las vistas de productos, variantes y stock
     */
    protected function createViews(): void
    {
        $this->createViewProducto();
        $this->createViewVariante();
        $this->createViewStock();
    }

    /**
     * Crea la vista principal de productos
     * 
     * @param string $viewName Nombre de la vista (por defecto: ListProducto)
     */
    protected function createViewProducto(string $viewName = 'ListProducto'): void
    {
        $this->addView($viewName, 'Producto', 'products', 'fa-solid fa-cubes')
            ->addOrderBy(['referencia'], 'reference')
            ->addOrderBy(['descripcion'], 'description')
            ->addOrderBy(['fechaalta'], 'creation-date')
            ->addOrderBy(['precio'], 'price')
            ->addOrderBy(['stockfis'], 'stock')
            ->addOrderBy(['actualizado'], 'update-time')
            ->addSearchFields(['referencia', 'descripcion', 'observaciones']);

        $translator = Translator::getInstance();

        // Filtros de estado
        $this->addFilterSelectWhere($viewName, 'status', [
            ['label' => $translator->trans('only-active'), 'where' => [new DataBaseWhere('bloqueado', false)]],
            ['label' => $translator->trans('blocked'), 'where' => [new DataBaseWhere('bloqueado', true)]],
            ['label' => $translator->trans('public'), 'where' => [new DataBaseWhere('publico', true)]],
            ['label' => $translator->trans('not-public'), 'where' => [new DataBaseWhere('publico', false)]],
            ['label' => $translator->trans('all'), 'where' => []]
        ]);

        // Filtros de fabricante
        $manufacturers = $this->codeModel->all('fabricantes', 'codfabricante', 'nombre');
        $this->addFilterSelect($viewName, 'codfabricante', 'manufacturer', 'codfabricante', $manufacturers);

        // Filtros de familia
        $families = $this->codeModel->all('familias', 'codfamilia', 'descripcion');
        $this->addFilterSelect($viewName, 'codfamilia', 'family', 'codfamilia', $families);

        // Filtros de tipo de producto
        $types = [['code' => '', 'description' => '------']];
        foreach (ProductType::all() as $key => $value) {
            $types[] = [
                'code' => $key,
                'description' => $translator->trans($value)
            ];
        }
        $this->addFilterSelect($viewName, 'tipo', 'type', 'tipo', $types);

        // Filtros de precio
        $this->addFilterNumber($viewName, 'min-price', 'price', 'precio', '>=');
        $this->addFilterNumber($viewName, 'max-price', 'price', 'precio', '<=');

        // Filtros de impuesto
        $taxes = Impuestos::codeModel();
        $this->addFilterSelect($viewName, 'codimpuesto', 'tax', 'codimpuesto', $taxes);

        // Filtros de excepción de IVA
        $exceptions = [['code' => '', 'description' => '------']];
        foreach (RegimenIVA::allExceptions() as $key => $value) {
            $exceptions[] = [
                'code' => $key,
                'description' => $translator->trans($value)
            ];
        }
        $this->addFilterSelect($viewName, 'excepcioniva', 'vat-exception', 'excepcioniva', $exceptions);

        // Filtros de stock
        $this->addFilterNumber($viewName, 'min-stock', 'stock', 'stockfis', '>=');
        $this->addFilterNumber($viewName, 'max-stock', 'stock', 'stockfis', '<=');

        // Filtros de propiedades
        $this->addFilterCheckbox($viewName, 'nostock', 'no-stock', 'nostock');
        $this->addFilterCheckbox($viewName, 'ventasinstock', 'allow-sale-without-stock', 'ventasinstock');
        $this->addFilterCheckbox($viewName, 'secompra', 'for-purchase', 'secompra');
        $this->addFilterCheckbox($viewName, 'sevende', 'for-sale', 'sevende');
    }

    /**
     * Crea la vista de variantes de productos
     * 
     * @param string $viewName Nombre de la vista (por defecto: ListVariante)
     */
    protected function createViewVariante(string $viewName = 'ListVariante'): void
    {
        $this->addView($viewName, 'Join\VarianteProducto', 'variants', 'fa-solid fa-project-diagram')
            ->addOrderBy(['variantes.referencia'], 'reference')
            ->addOrderBy(['variantes.codbarras'], 'barcode')
            ->addOrderBy(['variantes.precio'], 'price')
            ->addOrderBy(['variantes.coste'], 'cost-price')
            ->addOrderBy(['variantes.stockfis'], 'stock')
            ->addOrderBy(['productos.descripcion', 'variantes.referencia'], 'product')
            ->addSearchFields(['variantes.referencia', 'variantes.codbarras', 'productos.descripcion']);

        // Filtros de fabricante
        $manufacturers = $this->codeModel->all('fabricantes', 'codfabricante', 'nombre');
        $this->addFilterSelect($viewName, 'codfabricante', 'manufacturer', 'codfabricante', $manufacturers);

        // Filtros de familia
        $families = $this->codeModel->all('familias', 'codfamilia', 'descripcion');
        $this->addFilterSelect($viewName, 'codfamilia', 'family', 'codfamilia', $families);

        // Filtros de atributos
        $attributes1 = $this->getAttributesForFilter(1);
        $this->addFilterSelect($viewName, 'idatributovalor1', 'attribute-value-1', 'variantes.idatributovalor1', $attributes1);

        $attributes2 = $this->getAttributesForFilter(2);
        $this->addFilterSelect($viewName, 'idatributovalor2', 'attribute-value-2', 'variantes.idatributovalor2', $attributes2);

        $attributes3 = $this->getAttributesForFilter(3);
        $this->addFilterSelect($viewName, 'idatributovalor3', 'attribute-value-3', 'variantes.idatributovalor3', $attributes3);

        $attributes4 = $this->getAttributesForFilter(4);
        $this->addFilterSelect($viewName, 'idatributovalor4', 'attribute-value-4', 'variantes.idatributovalor4', $attributes4);

        // Filtros de precio
        $this->addFilterNumber($viewName, 'min-price', 'price', 'variantes.precio', '>=');
        $this->addFilterNumber($viewName, 'max-price', 'price', 'variantes.precio', '<=');

        // Filtros de stock
        $this->addFilterNumber($viewName, 'min-stock', 'stock', 'variantes.stockfis', '>=');
        $this->addFilterNumber($viewName, 'max-stock', 'stock', 'variantes.stockfis', '<=');

        // Desactivar botones de nuevo y eliminar
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Crea la vista de stock
     * 
     * @param string $viewName Nombre de la vista (por defecto: ListStock)
     */
    protected function createViewStock(string $viewName = 'ListStock'): void
    {
        $this->addView($viewName, 'Join\StockProducto', 'stock', 'fa-solid fa-dolly')
            ->addOrderBy(['stocks.referencia'], 'reference')
            ->addOrderBy(['stocks.cantidad'], 'quantity')
            ->addOrderBy(['stocks.disponible'], 'available')
            ->addOrderBy(['stocks.reservada'], 'reserved')
            ->addOrderBy(['stocks.pterecibir'], 'pending-reception')
            ->addOrderBy(['productos.descripcion', 'stocks.referencia'], 'product')
            ->addSearchFields(['stocks.referencia', 'stocks.ubicacion', 'productos.descripcion']);

        // Filtro de almacén
        if (count(Almacenes::all()) > 1) {
            $warehouses = Almacenes::codeModel();
            $this->addFilterSelect($viewName, 'codalmacen', 'warehouse', 'stocks.codalmacen', $warehouses);
        } else {
            // Ocultar columna de almacén si solo hay uno
            $this->tab($viewName)->disableColumn('warehouse');
        }

        // Filtros de fabricante
        $manufacturers = $this->codeModel->all('fabricantes', 'codfabricante', 'nombre');
        $this->addFilterSelect($viewName, 'codfabricante', 'manufacturer', 'productos.codfabricante', $manufacturers);

        // Filtros de familia
        $families = $this->codeModel->all('familias', 'codfamilia', 'descripcion');
        $this->addFilterSelect($viewName, 'codfamilia', 'family', 'productos.codfamilia', $families);

        // Filtros de tipo de stock
        $translator = Translator::getInstance();
        $this->addFilterSelectWhere($viewName, 'type', [
            [
                'label' => $translator->trans('all'),
                'where' => []
            ],
            [
                'label' => '------',
                'where' => []
            ],
            [
                'label' => $translator->trans('under-minimums'),
                'where' => [new DataBaseWhere('stocks.disponible', 'field:stockmin', '<')]
            ],
            [
                'label' => $translator->trans('excess'),
                'where' => [new DataBaseWhere('stocks.disponible', 'field:stockmax', '>')]
            ]
        ]);

        // Filtros numéricos
        $this->addFilterNumber($viewName, 'min-stock', 'quantity', 'cantidad', '>=');
        $this->addFilterNumber($viewName, 'max-stock', 'quantity', 'cantidad', '<=');

        $this->addFilterNumber($viewName, 'min-reserved', 'reserved', 'reservada', '>=');
        $this->addFilterNumber($viewName, 'max-reserved', 'reserved', 'reservada', '<=');

        $this->addFilterNumber($viewName, 'min-pterecibir', 'pending-reception', 'pterecibir', '>=');
        $this->addFilterNumber($viewName, 'max-pterecibir', 'pending-reception', 'pterecibir', '<=');

        $this->addFilterNumber($viewName, 'min-disponible', 'available', 'disponible', '>=');
        $this->addFilterNumber($viewName, 'max-disponible', 'available', 'disponible', '<=');

        // Desactivar botones de nuevo y eliminar
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Obtiene los valores de atributos para un filtro específico
     * 
     * @param int $num Número del selector de atributo
     * @return array Lista de valores para el filtro
     */
    protected function getAttributesForFilter(int $num): array
    {
        $values = [];
        $attributeModel = new Atributo();

        // Buscar atributos que usen el selector especificado
        $where = [new DataBaseWhere('num_selector', $num)];
        foreach ($attributeModel->all($where) as $attribute) {
            foreach ($attribute->getValores() as $value) {
                $values[] = new CodeModel([
                    'code' => $value->id,
                    'description' => $value->descripcion,
                ]);
            }
        }

        // Si no hay ninguno, buscar atributos con selector 0
        if (empty($values)) {
            $where = [new DataBaseWhere('num_selector', 0)];
            foreach ($attributeModel->all($where) as $attribute) {
                foreach ($attribute->getValores() as $value) {
                    $values[] = new CodeModel([
                        'code' => $value->id,
                        'description' => $value->descripcion,
                    ]);
                }
            }
        }

        // Agregar valor vacío al inicio
        array_unshift($values, new CodeModel([
            'code' => '',
            'description' => '------'
        ]));

        return $values;
    }
}