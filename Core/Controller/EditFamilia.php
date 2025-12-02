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
 * Controlador para editar un elemento individual del modelo Familia
 * 
 * Gestiona la información de familias de productos, permitiendo asociar y
 * desasociar productos, ver subfamilias y aplicar filtros avanzados.
 */
class EditFamilia extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Familia';
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
        $pageData['title'] = 'family';
        $pageData['icon'] = 'fa-solid fa-sitemap';
        
        return $pageData;
    }

    /**
     * Agrega productos seleccionados a la familia actual
     */
    protected function addProductAction(): void
    {
        $codigos = $this->request->request->getArray('codes');
        if (false === is_array($codigos)) {
            return;
        }

        $contador = 0;
        foreach ($codigos as $codigo) {
            $producto = new Producto();
            if (false === $producto->loadFromCode($codigo)) {
                continue;
            }

            $producto->codfamilia = $this->request->query('code');
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
        $this->createViewFamilies();
    }

    /**
     * Crea la vista de subfamilias
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewFamilies(string $viewName = 'ListFamilia'): void
    {
        $this->addListView($viewName, 'Familia', 'subfamilies', 'fa-solid fa-sitemap');
        $this->views[$viewName]->addOrderBy(['codfamilia'], 'code');

        $this->views[$viewName]->disableColumn('parent');
        $this->setSettings($viewName, 'btnDelete', false);
    }

    /**
     * Crea la vista de productos sin familia asignada
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewNewProducts(string $viewName = 'ListProducto-new'): void
    {
        $this->addListView($viewName, 'Producto', 'add', 'fa-solid fa-folder-plus');
        $this->createViewProductsCommon($viewName);

        $this->addButton($viewName, [
            'action' => 'add-product',
            'color' => 'success',
            'icon' => 'fa-solid fa-folder-plus',
            'label' => 'add'
        ]);
    }

    /**
     * Crea la vista de productos de la familia
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewProducts(string $viewName = 'ListProducto'): void
    {
        $this->addListView($viewName, 'Producto', 'products', 'fa-solid fa-cubes');
        $this->createViewProductsCommon($viewName);

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
        $this->views[$viewName]->addSearchFields(['descripcion', 'referencia']);
        $this->views[$viewName]->addOrderBy(['referencia'], 'reference', 1);
        $this->views[$viewName]->addOrderBy(['precio'], 'price');
        $this->views[$viewName]->addOrderBy(['stockfis'], 'stock');

        $idioma = Tools::lang();
        $this->views[$viewName]->addFilterSelectWhere('status', [
            ['label' => $idioma->trans('only-active'), 'where' => [new DataBaseWhere('bloqueado', false)]],
            ['label' => $idioma->trans('blocked'), 'where' => [new DataBaseWhere('bloqueado', true)]],
            ['label' => $idioma->trans('public'), 'where' => [new DataBaseWhere('publico', true)]],
            ['label' => $idioma->trans('all'), 'where' => []]
        ]);

        $fabricantes = $this->codeModel->all('fabricantes', 'codfabricante', 'nombre');
        $this->views[$viewName]->addFilterSelect('codfabricante', 'manufacturer', 'codfabricante', $fabricantes);

        $this->views[$viewName]->addFilterNumber('min-price', 'price', 'precio', '<=');
        $this->views[$viewName]->addFilterNumber('max-price', 'price', 'precio', '>=');
        $this->views[$viewName]->addFilterNumber('min-stock', 'stock', 'stockfis', '<=');
        $this->views[$viewName]->addFilterNumber('max-stock', 'stock', 'stockfis', '>=');

        $impuestos = Impuestos::codeModel();
        $this->views[$viewName]->addFilterSelect('codimpuesto', 'tax', 'codimpuesto', $impuestos);

        $this->views[$viewName]->addFilterCheckbox('nostock', 'no-stock', 'nostock');
        $this->views[$viewName]->addFilterCheckbox('ventasinstock', 'allow-sale-without-stock', 'ventasinstock');
        $this->views[$viewName]->addFilterCheckbox('secompra', 'for-purchase', 'secompra');
        $this->views[$viewName]->addFilterCheckbox('sevende', 'for-sale', 'sevende');
        $this->views[$viewName]->addFilterCheckbox('publico', 'public', 'publico');

        $this->views[$viewName]->disableColumn('family');
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
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
        $codfamilia = $this->getViewModelValue($this->getMainViewName(), 'codfamilia');
        switch ($viewName) {
            case 'ListProducto':
                $filtro = [new DataBaseWhere('codfamilia', $codfamilia)];
                $view->loadData('', $filtro);
                break;

            case 'ListProducto-new':
                $filtro = [new DataBaseWhere('codfamilia', null, 'IS')];
                $view->loadData('', $filtro);
                break;

            case 'ListFamilia':
                $filtro = [new DataBaseWhere('madre', $codfamilia)];
                $view->loadData('', $filtro);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Quita productos seleccionados de la familia actual
     */
    protected function removeProductAction(): void
    {
        $codigos = $this->request->request->getArray('codes');
        if (false === is_array($codigos)) {
            return;
        }

        $contador = 0;
        foreach ($codigos as $codigo) {
            $producto = new Producto();
            if (false === $producto->loadFromCode($codigo)) {
                continue;
            }

            $producto->codfamilia = null;
            if ($producto->save()) {
                $contador++;
            }
        }

        Tools::log()->notice('items-removed-correctly', ['%num%' => $contador]);
    }
}