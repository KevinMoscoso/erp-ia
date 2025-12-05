<?php
/**
 * ERPIA - Modales para Documentos de Compras
 * Este archivo es parte de ERPIA, un sistema ERP de código abierto.
 * 
 * Copyright (C) 2025 ERPIA
 *
 * Este programa es software libre: puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General GNU como publicada por
 * la Free Software Foundation, ya sea la versión 3 de la Licencia, o
 * (a su elección) cualquier versión posterior.
 *
 * Este programa se distribuye con la esperanza de que sea útil,
 * pero SIN NINGUNA GARANTÍA; sin siquiera la garantía implícita de
 * COMERCIALIZACIÓN o IDONEIDAD PARA UN PROPÓSITO PARTICULAR. Consulte la
 * Licencia Pública General GNU para obtener más detalles.
 *
 * Debería haber recibido una copia de la Licencia Pública General GNU
 * junto con este programa. Si no es así, consulte <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Core\Lib\AjaxForms;

use ERPIA\Core\Base\DataBase;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Model\Base\PurchaseDocument;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\AtributoValor;
use ERPIA\Dinamic\Model\Fabricante;
use ERPIA\Dinamic\Model\Familia;
use ERPIA\Dinamic\Model\Proveedor;

/**
 * Clase para generar modales de proveedores y productos en documentos de compras.
 * 
 * @author ERPIA
 * @version 1.0
 */
class PurchasesModalHTML
{
    /** @var string */
    protected static $warehouseCode;

    /** @var string */
    protected static $currencyCode;

    /** @var string */
    protected static $manufacturerCode;

    /** @var string */
    protected static $familyCode;

    /** @var string */
    protected static $supplierCode;

    /** @var bool */
    protected static $purchasedBefore;

    /** @var array */
    protected static $attributeValues = [];

    /** @var string */
    protected static $sortOrder;

    /** @var string */
    protected static $searchTerm;

    /**
     * Aplica los datos del formulario a los filtros del modal.
     *
     * @param PurchaseDocument $model
     * @param array $formData
     */
    public static function apply(PurchaseDocument &$model, array $formData): void
    {
        self::$warehouseCode = $model->codalmacen;
        self::$currencyCode = $model->coddivisa;
        self::$manufacturerCode = $formData['fp_codfabricante'] ?? '';
        self::$familyCode = $formData['fp_codfamilia'] ?? '';
        self::$supplierCode = $model->codproveedor;
        self::$sortOrder = $formData['fp_orden'] ?? 'ref_asc';
        self::$purchasedBefore = (bool)($formData['fp_comprado'] ?? false);
        self::$searchTerm = isset($formData['fp_query'])
            ? Tools::noHtml(mb_strtolower($formData['fp_query'], 'UTF8'))
            : '';
    }

    /**
     * Renderiza los modales de proveedores y productos.
     *
     * @param PurchaseDocument $model
     * @param string $url
     * @return string
     */
    public static function render(PurchaseDocument $model, string $url = ''): string
    {
        self::$warehouseCode = $model->codalmacen;
        self::$currencyCode = $model->coddivisa;
        self::$supplierCode = $model->codproveedor;

        return $model->editable
            ? self::supplierModal($url) . self::productModal()
            : '';
    }

    /**
     * Renderiza la lista de productos para el modal.
     *
     * @return string
     */
    public static function renderProductList(): string
    {
        $tableRows = '';
        foreach (self::fetchProducts() as $row) {
            $rowClass = $row['nostock']
                ? 'table-info clickableRow'
                : ($row['disponible'] > 0 ? 'clickableRow' : 'table-warning clickableRow');
            
            $cost = $row['neto'] ?? $row['coste'];
            $referenceLabel = empty($row['refproveedor']) || $row['refproveedor'] === $row['referencia']
                ? '<b>' . $row['referencia'] . '</b>'
                : '<b>' . $row['referencia'] . '</b> <span class="badge bg-light">' . $row['refproveedor'] . '</span>';
            
            $description = Tools::textBreak($row['descripcion'], 120)
                . self::attributeValueDisplay($row['idatributovalor1'])
                . self::attributeValueDisplay($row['idatributovalor2'])
                . self::attributeValueDisplay($row['idatributovalor3'])
                . self::attributeValueDisplay($row['idatributovalor4']);

            $tableRows .= '<tr class="' . $rowClass . '" onclick="$(\'#findProductModal\').modal(\'hide\');'
                . ' return purchasesFormAction(\'add-product\', \'' . $row['referencia'] . '\');">'
                . '<td>' . $referenceLabel . ' ' . $description . '</td>'
                . '<td class="text-end">' . str_replace(' ', '&nbsp;', Tools::money($cost)) . '</td>'
                . '<td class="text-end">' . str_replace(' ', '&nbsp;', Tools::money($row['precio'])) . '</td>'
                . '<td class="text-end">' . $row['disponible'] . '</td>'
                . '</tr>';
        }

        if (empty($tableRows)) {
            $tableRows = '<tr class="table-warning"><td colspan="4">' . Tools::trans('no-data') . '</td></tr>';
        }

        return '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th>' . Tools::trans('product') . '</th>'
            . '<th class="text-end">' . Tools::trans('cost-price') . '</th>'
            . '<th class="text-end">' . Tools::trans('price') . '</th>'
            . '<th class="text-end">' . Tools::trans('stock') . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $tableRows . '</tbody>'
            . '</table>';
    }

    /**
     * Genera el selector de fabricantes.
     *
     * @return string
     */
    protected static function fabricantes(): string
    {
        $options = '<option value="">' . Tools::trans('manufacturer') . '</option>'
            . '<option value="">------</option>';
        
        $manufacturers = Fabricante::all([], ['nombre' => 'ASC'], 0, 0);
        foreach ($manufacturers as $manufacturer) {
            $options .= '<option value="' . $manufacturer->codfabricante . '">' . $manufacturer->nombre . '</option>';
        }

        return '<select name="fp_codfabricante" class="form-select" onchange="return purchasesFormAction(\'find-product\', \'0\');">'
            . $options . '</select>';
    }

    /**
     * Genera el selector de familias.
     *
     * @return string
     */
    protected static function familias(): string
    {
        $options = '<option value="">' . Tools::trans('family') . '</option>'
            . '<option value="">------</option>';

        $mainFamiliesWhere = [new DataBaseWhere('madre', null, 'IS')];
        $mainFamiliesOrder = ['descripcion' => 'ASC'];
        $mainFamilies = Familia::all($mainFamiliesWhere, $mainFamiliesOrder, 0, 0);

        foreach ($mainFamilies as $family) {
            $options .= '<option value="' . $family->codfamilia . '">' . $family->descripcion . '</option>';
            $options .= self::subfamilias($family);
        }

        return '<select name="fp_codfamilia" class="form-select" onchange="return purchasesFormAction(\'find-product\', \'0\');">'
            . $options . '</select>';
    }

    /**
     * Obtiene los productos filtrados.
     *
     * @return array
     */
    protected static function fetchProducts(): array
    {
        $db = new DataBase();
        $db->connect();

        $sql = 'SELECT v.referencia, pp.refproveedor, p.descripcion,'
            . ' v.idatributovalor1, v.idatributovalor2, v.idatributovalor3, v.idatributovalor4,'
            . ' v.coste, v.precio, pp.neto, COALESCE(s.disponible, 0) as disponible, p.nostock'
            . ' FROM variantes v'
            . ' LEFT JOIN productos p ON v.idproducto = p.idproducto'
            . ' LEFT JOIN stocks s ON v.referencia = s.referencia'
            . ' AND s.codalmacen = ' . $db->var2str(self::$warehouseCode)
            . ' LEFT JOIN productosprov pp ON pp.referencia = p.referencia'
            . ' AND pp.codproveedor = ' . $db->var2str(self::$supplierCode)
            . ' AND pp.coddivisa = ' . $db->var2str(self::$currencyCode)
            . ' WHERE p.secompra = true AND p.bloqueado = false';

        if (self::$manufacturerCode) {
            $sql .= ' AND codfabricante = ' . $db->var2str(self::$manufacturerCode);
        }

        if (self::$familyCode) {
            $familyCodes = [$db->var2str(self::$familyCode)];
            $familyModel = new Familia();
            
            if ($familyModel->load(self::$familyCode)) {
                foreach ($familyModel->getSubfamilias() as $subfamily) {
                    $familyCodes[] = $db->var2str($subfamily->codfamilia);
                }
            }

            $sql .= ' AND codfamilia IN (' . implode(',', $familyCodes) . ')';
        }

        if (self::$purchasedBefore) {
            $sql .= ' AND pp.codproveedor = ' . $db->var2str(self::$supplierCode);
        }

        if (self::$searchTerm) {
            $words = explode(' ', self::$searchTerm);
            if (count($words) === 1) {
                $sql .= " AND (LOWER(v.codbarras) = " . $db->var2str(self::$searchTerm)
                    . " OR LOWER(v.referencia) LIKE '%" . self::$searchTerm . "%'"
                    . " OR LOWER(pp.refproveedor) LIKE '%" . self::$searchTerm . "%'"
                    . " OR LOWER(p.descripcion) LIKE '%" . self::$searchTerm . "%')";
            } elseif (count($words) > 1) {
                $sql .= " AND (LOWER(v.referencia) LIKE '%" . self::$searchTerm . "%'"
                    . " OR LOWER(pp.refproveedor) LIKE '%" . self::$searchTerm . "%' OR (";
                foreach ($words as $index => $word) {
                    $sql .= $index > 0
                        ? " AND LOWER(p.descripcion) LIKE '%" . $word . "%'"
                        : "LOWER(p.descripcion) LIKE '%" . $word . "%'";
                }
                $sql .= "))";
            }
        }

        switch (self::$sortOrder) {
            case 'desc_asc':
                $sql .= " ORDER BY 3 ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY 9 DESC";
                break;
            case 'ref_asc':
                $sql .= " ORDER BY 1 ASC";
                break;
            case 'stock_desc':
                $sql .= " ORDER BY 11 DESC";
                break;
        }

        return $db->selectLimit($sql);
    }

    /**
     * Devuelve la descripción de un valor de atributo.
     *
     * @param int|null $attributeValueId
     * @return string
     */
    protected static function attributeValueDisplay(?int $attributeValueId): string
    {
        if (empty($attributeValueId)) {
            return '';
        }

        if (!isset(self::$attributeValues[$attributeValueId])) {
            $attValue = new AtributoValor();
            if ($attValue->load($attributeValueId)) {
                self::$attributeValues[$attributeValueId] = $attValue->descripcion;
            } else {
                self::$attributeValues[$attributeValueId] = '';
            }
        }

        return empty(self::$attributeValues[$attributeValueId]) ? '' : ', ' . self::$attributeValues[$attributeValueId];
    }

    /**
     * Genera el modal de productos.
     *
     * @return string
     */
    protected static function productModal(): string
    {
        return '<div class="modal" id="findProductModal" tabindex="-1" aria-hidden="true">'
            . '<div class="modal-dialog modal-xl">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fa-solid fa-cubes fa-fw"></i> ' . Tools::trans('products') . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="row g-2">'
            . '<div class="col-sm-6 col-md-12 col-lg mb-2">'
            . '<div class="input-group">'
            . '<input type="text" name="fp_query" class="form-control" id="productModalInput" placeholder="' . Tools::trans('search')
            . '" onkeyup="return purchasesFormActionWait(\'find-product\', \'0\', event);"/>'
            . '<button class="btn btn-primary btn-spin-action" type="button" onclick="return purchasesFormAction(\'find-product\', \'0\');">'
            . '<i class="fa-solid fa-search"></i></button>'
            . '</div>'
            . '</div>'
            . '<div class="col-sm-6 col-md-4 col-lg mb-2">' . self::fabricantes() . '</div>'
            . '<div class="col-sm-6 col-md-4 col-lg mb-2">' . self::familias() . '</div>'
            . '<div class="col-sm-6 col-md-4 col-lg mb-2">' . self::orden() . '</div>'
            . '</div>'
            . '<div class="row g-2">'
            . '<div class="col-sm">'
            . '<div class="form-check">'
            . '<input type="checkbox" name="fp_comprado" value="1" class="form-check-input" id="comprado" onchange="return purchasesFormAction(\'find-product\', \'0\');">'
            . '<label class="form-check-label" for="comprado">' . Tools::trans('previously-purchased-from-supplier') . '</label>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="table-responsive" id="findProductList">' . self::renderProductList() . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el modal de proveedores.
     *
     * @param string $url
     * @return string
     */
    protected static function supplierModal(string $url): string
    {
        $tableRows = '';
        $where = [new DataBaseWhere('fechabaja', null, 'IS')];
        $suppliers = Proveedor::all($where, ['LOWER(nombre)' => 'ASC'], 0, 50);

        foreach ($suppliers as $supplier) {
            $displayName = ($supplier->nombre === $supplier->razonsocial)
                ? $supplier->nombre
                : $supplier->nombre . ' <small>(' . $supplier->razonsocial . ')</span>';
            
            $tableRows .= '<tr class="clickableRow" onclick="document.forms[\'purchasesForm\'][\'codproveedor\'].value = \''
                . $supplier->codproveedor . '\'; $(\'#findSupplierModal\').modal(\'hide\'); purchasesFormAction(\'set-supplier\', \'0\'); return false;">'
                . '<td><i class="fa-solid fa-user fa-fw"></i> ' . $displayName . '</td>'
                . '</tr>';
        }

        return '<div class="modal" id="findSupplierModal" tabindex="-1" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-scrollable">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fa-solid fa-users fa-fw"></i> ' . Tools::trans('suppliers') . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body p-0">'
            . '<div class="p-3">'
            . '<div class="input-group">'
            . '<input type="text" id="findSupplierInput" class="form-control" placeholder="' . Tools::trans('search') . '" />'
            . '<div class="input-group-append">'
            . '<button type="button" class="btn btn-primary"><i class="fa-solid fa-search"></i></button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<table class="table table-hover mb-0">' . $tableRows . '</table></div>'
            . '<div class="modal-footer bg-light">'
            . '<a href="EditProveedor?return=' . urlencode($url) . '" class="btn w-100 btn-success">'
            . '<i class="fa-solid fa-plus fa-fw"></i> ' . Tools::trans('new')
            . '</a>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el selector de ordenación.
     *
     * @return string
     */
    protected static function orden(): string
    {
        return '<div class="input-group">'
            . '<span class="input-group-text"><i class="fa-solid fa-sort-amount-down-alt"></i></span>'
            . '<select name="fp_orden" class="form-select" onchange="return purchasesFormAction(\'find-product\', \'0\');">'
            . '<option value="">' . Tools::trans('sort') . '</option>'
            . '<option value="">------</option>'
            . '<option value="ref_asc">' . Tools::trans('reference') . '</option>'
            . '<option value="desc_asc">' . Tools::trans('description') . '</option>'
            . '<option value="price_desc">' . Tools::trans('price') . '</option>'
            . '<option value="stock_desc">' . Tools::trans('stock') . '</option>'
            . '</select>'
            . '</div>';
    }

    /**
     * Genera las opciones de subfamilias de forma recursiva.
     *
     * @param Familia $family
     * @param int $level
     * @return string
     */
    private static function subfamilias(Familia $family, int $level = 1): string
    {
        $options = '';
        $subfamilies = $family->getSubfamilias();

        foreach ($subfamilies as $subfamily) {
            $options .= '<option value="' . $subfamily->codfamilia . '">'
                . str_repeat('-', $level) . ' ' . $subfamily->descripcion
                . '</option>';
            $options .= self::subfamilias($subfamily, $level + 1);
        }

        return $options;
    }
}