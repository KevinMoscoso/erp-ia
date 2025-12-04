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
use ERPIA\Core\DataSrc\Almacenes;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\DocFilesTrait;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\Lib\ExtendedController\ProductImagesTrait;
use ERPIA\Core\Lib\ProductType;
use ERPIA\Dinamic\Model\ProductoImagen;
use ERPIA\Dinamic\Lib\RegimenIVA;
use ERPIA\Dinamic\Model\Atributo;
use ERPIA\Dinamic\Model\CodeModel;

/**
 * Controlador para editar un elemento individual del modelo Producto
 * 
 * Gestiona productos con variantes, imágenes, archivos, stock, proveedores
 * y pedidos asociados, utilizando widgets personalizados y traits especializados.
 */
class EditProducto extends EditController
{
    use DocFilesTrait;
    use ProductImagesTrait;

    /** @var array */
    private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Producto';
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
        $pageData['title'] = 'product';
        $pageData['icon'] = 'fa-solid fa-cube';
        
        return $pageData;
    }

    /**
     * Configura las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();

        CodeModel::setLimit(9999);

        $this->createViewsVariants();
        $this->createViewsProductImages();
        $this->createViewDocFiles();
        $this->createViewsStock();
        $this->createViewsPedidosClientes();
        $this->createViewsPedidosProveedores();
        $this->createViewsSuppliers();
    }

    /**
     * Crea la vista de pedidos de clientes
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsPedidosClientes(string $viewName = 'ListLineaPedidoCliente'): void
    {
        $this->addListView($viewName, 'LineaPedidoCliente', 'reserved', 'fa-solid fa-lock')
            ->addSearchFields(['referencia', 'descripcion'])
            ->addOrderBy(['referencia'], 'reference')
            ->addOrderBy(['cantidad'], 'quantity')
            ->addOrderBy(['servido'], 'quantity-served')
            ->addOrderBy(['descripcion'], 'description')
            ->addOrderBy(['pvptotal'], 'amount')
            ->addOrderBy(['idlinea'], 'code', 2);

        $this->views[$viewName]->disableColumn('product');
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'checkBoxes', false);
    }

    /**
     * Crea la vista de pedidos de proveedores
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsPedidosProveedores(string $viewName = 'ListLineaPedidoProveedor'): void
    {
        $this->addListView($viewName, 'LineaPedidoProveedor', 'pending-reception', 'fa-solid fa-ship')
            ->addSearchFields(['referencia', 'descripcion'])
            ->addOrderBy(['referencia'], 'reference')
            ->addOrderBy(['cantidad'], 'quantity')
            ->addOrderBy(['servido'], 'quantity-served')
            ->addOrderBy(['descripcion'], 'description')
            ->addOrderBy(['pvptotal'], 'amount')
            ->addOrderBy(['idlinea'], 'code', 2);

        $this->views[$viewName]->disableColumn('product');
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'checkBoxes', false);
    }

    /**
     * Crea la vista de stock
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsStock(string $viewName = 'EditStock'): void
    {
        $this->addEditListView($viewName, 'Stock', 'stock', 'fa-solid fa-dolly');

        if (count(Almacenes::all()) <= 1) {
            $this->views[$viewName]->disableColumn('warehouse');
        }
    }

    /**
     * Crea la vista de proveedores del producto
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsSuppliers(string $viewName = 'EditProductoProveedor'): void
    {
        $this->addEditListView($viewName, 'ProductoProveedor', 'suppliers', 'fa-solid fa-users');
    }

    /**
     * Crea la vista de variantes
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewsVariants(string $viewName = 'EditVariante'): void
    {
        $this->addEditListView($viewName, 'Variante', 'variants', 'fa-solid fa-project-diagram');

        $atributo = new Atributo();
        $contadorAtributos = $atributo->count();
        if ($contadorAtributos < 4) {
            $this->views[$viewName]->disableColumn('attribute-value-4');
        }
        if ($contadorAtributos < 3) {
            $this->views[$viewName]->disableColumn('attribute-value-3');
        }
        if ($contadorAtributos < 2) {
            $this->views[$viewName]->disableColumn('attribute-value-2');
        }
        if ($contadorAtributos < 1) {
            $this->views[$viewName]->disableColumn('attribute-value-1');
        }
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
            case 'add-file':
                return $this->addFileAction();
            case 'add-image':
                return $this->addImageAction();
            case 'delete-file':
                return $this->deleteFileAction();
            case 'delete-image':
                return $this->deleteImageAction();
            case 'edit-file':
                return $this->editFileAction();
            case 'sort-images':
                return $this->sortImagesAction();
            case 'unlink-file':
                return $this->unlinkFileAction();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Acción para insertar un nuevo registro
     * 
     * @return bool
     */
    protected function insertAction(): bool
    {
        if (parent::insertAction()) {
            return true;
        }

        if ($this->active === 'EditProducto') {
            $this->views['EditProducto']->disableColumn('reference', false, 'false');
        }

        return false;
    }

    /**
     * Configura los widgets de atributos personalizados
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function loadCustomAttributeWidgets(string $viewName): void
    {
        $nombresColumnas = ['attribute-value-1', 'attribute-value-2', 'attribute-value-3', 'attribute-value-4'];
        foreach ($nombresColumnas as $indice => $nombreColumna) {
            $columna = $this->views[$viewName]->columnForName($nombreColumna);
            if ($columna && $columna->widget->getType() === 'select') {
                $numeroSelector = $indice + 1;
                $atributos = Atributo::all([
                    new DataBaseWhere('num_selector', $numeroSelector),
                ]);

                if (count($atributos) === 0) {
                    $atributos = Atributo::all([
                        new DataBaseWhere('num_selector', 0),
                    ]);
                }

                $valoresAtributos = [];
                foreach ($atributos as $atributo) {
                    if (count($valoresAtributos) > 0) {
                        $valoresAtributos[] = [
                            'value' => '',
                            'title' => '------',
                        ];
                    }

                    foreach ($atributo->getValores() as $valor) {
                        $valoresAtributos[] = [
                            'value' => $valor->id,
                            'title' => $valor->descripcion,
                        ];
                    }
                }

                $columna->widget->setValuesFromArray($valoresAtributos, false, true);
            }
        }
    }

    /**
     * Configura el widget de referencia personalizado
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function loadCustomReferenceWidget(string $viewName): void
    {
        $referencias = [];
        $idProducto = $this->getViewModelValue('EditProducto', 'idproducto');
        $filtro = [new DataBaseWhere('idproducto', $idProducto)];
        $valores = $this->codeModel->all('variantes', 'referencia', 'referencia', false, $filtro);
        foreach ($valores as $codigo) {
            $referencias[] = ['value' => $codigo->code, 'title' => $codigo->description];
        }

        $columna = $this->views[$viewName]->columnForName('reference');
        if ($columna && $columna->widget->getType() === 'select') {
            $columna->widget->setValuesFromArray($referencias, false);
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
        $idProducto = $this->getViewModelValue('EditProducto', 'idproducto');
        $filtro = [new DataBaseWhere('idproducto', $idProducto)];

        switch ($viewName) {
            case 'docfiles':
                $this->loadDataDocFiles($view, $this->getModelClassName(), $this->getModel()->primaryColumnValue());
                break;

            case $this->getMainViewName():
                parent::loadData($viewName, $view);
                $this->loadTypes($viewName);
                $this->loadExceptionVat($viewName);
                if (empty($view->model->primaryColumnValue())) {
                    $view->disableColumn('stock');
                }
                if ($view->model->nostock) {
                    $this->setSettings('EditStock', 'active', false);
                }
                $this->loadCustomReferenceWidget('EditProductoProveedor');
                $this->loadCustomReferenceWidget('EditStock');
                if (false === empty($view->model->primaryColumnValue())) {
                    $this->addButton($viewName, [
                        'action' => 'CopyModel?model=' . $this->getModelClassName() . '&code=' . $view->model->primaryColumnValue(),
                        'icon' => 'fa-solid fa-cut',
                        'label' => 'copy',
                        'type' => 'link'
                    ]);
                }
                break;

            case 'EditProductoImagen':
                $orden = ['orden' => 'ASC'];
                $view->loadData('', $filtro, $orden, 0, 0);
                break;

            case 'EditVariante':
                $view->loadData('', $filtro, ['idvariante' => 'DESC']);
                $this->loadCustomAttributeWidgets($viewName);
                break;

            case 'EditStock':
                $view->loadData('', $filtro, ['idstock' => 'DESC']);
                break;

            case 'EditProductoProveedor':
                $view->loadData('', $filtro, ['id' => 'DESC']);
                break;

            case 'ListLineaPedidoCliente':
                $filtro[] = new DataBaseWhere('actualizastock', -2);
                $view->loadData('', $filtro);
                $this->setSettings($viewName, 'active', $view->model->count($filtro) > 0);
                break;

            case 'ListLineaPedidoProveedor':
                $filtro[] = new DataBaseWhere('actualizastock', 2);
                $view->loadData('', $filtro);
                $this->setSettings($viewName, 'active', $view->model->count($filtro) > 0);
                break;
        }
    }

    /**
     * Configura los valores del widget de tipos de producto
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function loadTypes(string $viewName): void
    {
        $columna = $this->views[$viewName]->columnForName('type');
        if ($columna && $columna->widget->getType() === 'select') {
            $columna->widget->setValuesFromArrayKeys(ProductType::all(), true, true);
        }
    }

    /**
     * Configura los valores del widget de excepción de IVA
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function loadExceptionVat(string $viewName): void
    {
        $columna = $this->views[$viewName]->columnForName('vat-exception');
        if ($columna && $columna->widget->getType() === 'select') {
            $columna->widget->setValuesFromArrayKeys(RegimenIVA::allExceptions(), true, true);
        }
    }

    /**
     * Ordena las imágenes del producto
     * 
     * @return bool
     */
    protected function sortImagesAction(): bool
    {
        $idsOrdenadas = $this->request->request->getArray('orden', false);
        if (!empty($idsOrdenadas) && is_array($idsOrdenadas)) {
            $orden = 1;
            foreach ($idsOrdenadas as $idImagen) {
                $imagenProducto = new ProductoImagen();
                $imagenProducto->load($idImagen);
                $imagenProducto->orden = $orden;
                if ($imagenProducto->save()) {
                    $orden++;
                }
            }
        }
        $this->setTemplate(false);
        $this->response->json(['status' => 'ok']);
        return false;
    }
}