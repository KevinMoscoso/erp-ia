<?php
/**
 * Este archivo es parte de ERPIA
 * Copyright (C) 2024-2025 ERPIA Team
 *
 * Este programa es software libre: puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General GNU Affero como
 * publicada por la Free Software Foundation, ya sea la versión 3 de la
 * Licencia, o (a su opción) cualquier versión posterior.
 *
 * Este programa se distribuye con la esperanza de que sea útil,
 * pero SIN NINGUNA GARANTÍA; sin siquiera la garantía implícita de
 * COMERCIABILIDAD o IDONEIDAD PARA UN PROPÓSITO PARTICULAR. Consulte la
 * Licencia Pública General GNU Affero para más detalles.
 *
 * Debería haber recibido una copia de la Licencia Pública General GNU Affero
 * junto con este programa. Si no es así, consulte <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Impuestos;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Producto;

/**
 * Controlador para editar un elemento individual del modelo Fabricante
 * 
 * Gestiona la información de fabricantes y permite asociar o desasociar
 * productos de manera masiva mediante dos vistas especializadas.
 */
class EditFabricante extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Fabricante';
    }

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'manufacturer';
        $pageData['icon'] = 'fa-solid fa-industry';
        
        return $pageData;
    }

    /**
     * Agrega productos seleccionados al fabricante actual
     */
    protected function addProductAction(): void
    {
        $contador = 0;
        $codigos = $this->request->request->getArray('codes', false);
        foreach ($codigos as $codigo) {
            $producto = new Producto();
            if (false === $producto->loadFromCode($codigo)) {
                continue;
            }

            $producto->codfabricante = $this->request->query('code');
            if ($producto->save()) {
                $contador++;
            }
        }

        Tools::log()->notice('items-added-correctly', ['%num%' => $contador]);
    }

    /**
     * Configura las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewProducts();
        $this->createViewNewProducts();
    }

    /**
     * Crea la vista de productos sin fabricante asignado
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewNewProducts(string $viewName = 'ListProducto-new'): void
    {
        $this->addListView($viewName, 'Producto', 'add', 'fa-solid fa-folder-plus');
        $this->createViewProductsCommon($viewName);

        // Botón para añadir productos
        $this->addButton($viewName, [
            'action' => 'add-product',
            'color' => 'success',
            'icon' => 'fa-solid fa-folder-plus',
            'label' => 'add'
        ]);
    }

    /**
     * Crea la vista de productos asociados al fabricante
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewProducts(string $viewName = 'ListProducto'): void
    {
        $this->addListView($viewName, 'Producto', 'products', 'fa-solid fa-cubes');
        $this->createViewProductsCommon($viewName);

        // Botón para quitar productos
        $this->addButton($viewName, [
            'action' => 'remove-product',
            'color' => 'danger',
            'confirm' => true,
            'icon' => 'fa-solid fa-folder-minus',
            'label' => 'remove-from-list'
        ]);
    }

    /**
     * Configura los elementos comunes de las vistas de productos
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewProductsCommon(string $viewName): void
    {
        $this->listView($viewName)->addSearchFields(['descripcion', 'referencia'])
            ->addOrderBy(['referencia'], 'reference', 1)
            ->addOrderBy(['precio'], 'price')
            ->addOrderBy(['stockfis'], 'stock');

        $idioma = Tools::lang();
        $familias = $this->codeModel->all('familias', 'codfamilia', 'descripcion');
        $impuestos = Impuestos::codeModel();

        // Filtros
        $this->listView($viewName)
            ->addFilterSelectWhere('status', [
                ['label' => $idioma->trans('only-active'), 'where' => [new DataBaseWhere('bloqueado', false)]],
                ['label' => $idioma->trans('blocked'), 'where' => [new DataBaseWhere('bloqueado', true)]],
                ['label' => $idioma->trans('public'), 'where' => [new DataBaseWhere('publico', true)]],
                ['label' => $idioma->trans('all'), 'where' => []]
            ])
            ->addFilterSelect('codfamilia', 'family', 'codfamilia', $familias)
            ->addFilterNumber('min-price', 'price', 'precio', '<=')
            ->addFilterNumber('max-price', 'price', 'precio', '>=')
            ->addFilterNumber('min-stock', 'stock', 'stockfis', '<=')
            ->addFilterNumber('max-stock', 'stock', 'stockfis', '>=')
            ->addFilterSelect('codimpuesto', 'tax', 'codimpuesto', $impuestos)
            ->addFilterCheckbox('nostock', 'no-stock', 'nostock')
            ->addFilterCheckbox('ventasinstock', 'allow-sale-without-stock', 'ventasinstock')
            ->addFilterCheckbox('secompra', 'for-purchase', 'secompra')
            ->addFilterCheckbox('sevende', 'for-sale', 'sevende')
            ->addFilterCheckbox('publico', 'public', 'publico');

        // Ocultar columna de fabricante y deshabilitar botones
        $this->tab($viewName)
            ->disableColumn('manufacturer')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false);
    }

    /**
     * Ejecuta acciones previas
     * 
     * @param string $action Acción a ejecutar
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-product':
                $this->addProductAction();
                return true;

            case 'remove-product':
                $this->removeProductAction();
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Carga datos en una vista específica
     * 
     * @param string $viewName Nombre de la vista
     * @param BaseView $view Instancia de la vista
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListProducto':
                $codfabricante = $this->getViewModelValue($this->getMainViewName(), 'codfabricante');
                $filtro = [new DataBaseWhere('codfabricante', $codfabricante)];
                $view->loadData('', $filtro);
                break;

            case 'ListProducto-new':
                $filtro = [new DataBaseWhere('codfabricante', null, 'IS')];
                $view->loadData('', $filtro);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Quita productos seleccionados del fabricante actual
     */
    protected function removeProductAction(): void
    {
        $contador = 0;
        $codigos = $this->request->request->getArray('codes', false);
        foreach ($codigos as $codigo) {
            $producto = new Producto();
            if (false === $producto->loadFromCode($codigo)) {
                continue;
            }

            $producto->codfabricante = null;
            if ($producto->save()) {
                $contador++;
            }
        }

        Tools::log()->notice('items-removed-correctly', ['%num%' => $contador]);
    }
}