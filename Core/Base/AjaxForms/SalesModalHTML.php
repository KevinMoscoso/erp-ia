<?php
namespace ERPIA\Core\Base\AjaxForms;

use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Base\DataBase;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Base\Translator;
use ERPIA\Core\Cache;
use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Model\User;
use ERPIA\Core\Tools;
use EERPIARP\Dinamic\Model\AtributoValor;
use ERPIA\Dinamic\Model\Cliente;
use ERPIA\Dinamic\Model\Fabricante;
use ERPIA\Dinamic\Model\Familia;
use ERPIA\Dinamic\Model\RoleAccess;

class SalesModalHTML
{
    /** @var string */
    protected static $codalmacen;

    /** @var string */
    protected static $codcliente;

    /** @var string */
    protected static $codfabricante;

    /** @var string */
    protected static $codfamilia;

    /** @var array */
    protected static $idatributovalores = [];

    /** @var string */
    protected static $orden;

    /** @var string */
    protected static $query;

    /** @var bool */
    protected static $vendido;

    public static function apply(SalesDocument &$model, array $formData)
    {
        self::$codalmacen = $model->codalmacen;
        self::$codcliente = $model->codcliente;
        self::$codfabricante = $formData['fp_codfabricante'] ?? '';
        self::$codfamilia = $formData['fp_codfamilia'] ?? '';
        self::$orden = $formData['fp_orden'] ?? 'ref_asc';
        self::$vendido = (bool)($formData['fp_vendido'] ?? false);
        self::$query = isset($formData['fp_query']) ?
            Tools::noHtml(mb_strtolower($formData['fp_query'], 'UTF8')) : '';
    }

    public static function render(SalesDocument $model, string $url, User $user, ControllerPermissions $permissions): string
    {
        self::$codalmacen = $model->codalmacen;

        $i18n = new Translator();
        return $model->editable ? 
            self::renderCustomerModal($i18n, $url, $user, $permissions) . 
            self::renderProductModal($i18n) : '';
    }

    public static function renderProductList(): string
    {
        $i18n = new Translator();
        $productos = self::fetchProducts();
        
        if (empty($productos)) {
            return '<table class="table table-hover mb-0">'
                . '<tbody><tr class="table-warning"><td colspan="4">' . $i18n->trans('no-data') . '</td></tr></tbody>'
                . '</table>';
        }

        $thead = '<thead><tr>'
            . '<th>' . $i18n->trans('product') . '</th>'
            . '<th class="text-right">' . $i18n->trans('price') . '</th>';
        
        if (self::$vendido) {
            $thead .= '<th class="text-right">' . $i18n->trans('last-price-sale') . '</th>';
        }
        
        $thead .= '<th class="text-right">' . $i18n->trans('stock') . '</th>'
            . '</tr></thead>';

        $tbody = '<tbody>';
        foreach ($productos as $producto) {
            $claseFila = $producto['nostock'] ? 
                'table-info clickableRow' : 
                ($producto['disponible'] > 0 ? 'clickableRow' : 'table-warning clickableRow');
            
            $descripcion = Tools::textBreak($producto['descripcion'], 120)
                . self::obtenerAtributo($producto['idatributovalor1'])
                . self::obtenerAtributo($producto['idatributovalor2'])
                . self::obtenerAtributo($producto['idatributovalor3'])
                . self::obtenerAtributo($producto['idatributovalor4']);

            $tbody .= '<tr class="' . $claseFila . '" onclick="$(\'#findProductModal\').modal(\'hide\');'
                . ' return salesFormAction(\'add-product\', \'' . $producto['referencia'] . '\');">'
                . '<td><b>' . $producto['referencia'] . '</b> ' . $descripcion . '</td>'
                . '<td class="text-right">' . str_replace(' ', '&nbsp;', Tools::money($producto['precio'])) . '</td>';

            if (self::$vendido) {
                $tbody .= '<td class="text-right">' . str_replace(' ', '&nbsp;', Tools::money($producto['ultimo_precio'])) . '</td>';
            }

            $tbody .= '<td class="text-right">' . $producto['disponible'] . '</td>'
                . '</tr>';
        }
        $tbody .= '</tbody>';

        return '<table class="table table-hover mb-0">' . $thead . $tbody . '</table>';
    }

    protected static function renderManufacturerFilter(Translator $i18n): string
    {
        $fabricante = new Fabricante();
        $opciones = '<option value="">' . $i18n->trans('manufacturer') . '</option>'
            . '<option value="">------</option>';
        
        $fabricantes = $fabricante->all([], ['nombre' => 'ASC'], 0, 0);
        foreach ($fabricantes as $fab) {
            $opciones .= '<option value="' . $fab->codfabricante . '">' . $fab->nombre . '</option>';
        }

        return '<select name="fp_codfabricante" class="form-control" onchange="return salesFormAction(\'find-product\', \'0\');">'
            . $opciones . '</select>';
    }

    protected static function renderFamilyFilter(Translator $i18n): string
    {
        $opciones = '<option value="">' . $i18n->trans('family') . '</option>'
            . '<option value="">------</option>';

        $familia = new Familia();
        $wherePrincipal = [new DataBaseWhere('madre', null, 'IS')];
        $familiasPrincipales = $familia->all($wherePrincipal, ['descripcion' => 'ASC'], 0, 0);
        
        foreach ($familiasPrincipales as $fam) {
            $opciones .= '<option value="' . $fam->codfamilia . '">' . $fam->descripcion . '</option>';
            $opciones .= self::renderSubfamilias($fam, $i18n);
        }

        return '<select name="fp_codfamilia" class="form-control" onchange="return salesFormAction(\'find-product\', \'0\');">'
            . $opciones . '</select>';
    }

    protected static function getClientes(User $user, ControllerPermissions $permissions): array
    {
        $cacheKey = 'sales-modal-clientes-' . $user->nick;
        $clientesCache = Cache::get($cacheKey);
        
        if (is_array($clientesCache)) {
            return $clientesCache;
        }

        $permisoCompleto = false;
        $accesos = RoleAccess::allFromUser($user->nick, 'EditCliente');
        foreach ($accesos as $acceso) {
            if (!$acceso->onlyownerdata) {
                $permisoCompleto = true;
                break;
            }
        }

        $cliente = new Cliente();
        $condiciones = [new DataBaseWhere('fechabaja', null, 'IS')];
        
        if ($permissions->onlyOwnerData && !$permisoCompleto) {
            $condiciones[] = new DataBaseWhere('codagente', $user->codagente);
            $condiciones[] = new DataBaseWhere('codagente', null, 'IS NOT');
        }

        $clientes = $cliente->all($condiciones, ['LOWER(nombre)' => 'ASC']);
        Cache::set($cacheKey, $clientes);
        
        return $clientes;
    }

    protected static function fetchProducts(): array
    {
        $db = new DataBase();
        $sql = "SELECT v.referencia, p.descripcion, v.idatributovalor1, v.idatributovalor2, 
                v.idatributovalor3, v.idatributovalor4, v.precio, 
                COALESCE(s.disponible, 0) as disponible, p.nostock
                FROM variantes v
                LEFT JOIN productos p ON v.idproducto = p.idproducto
                LEFT JOIN stocks s ON v.referencia = s.referencia AND s.codalmacen = " . $db->var2str(self::$codalmacen) . "
                WHERE p.sevende = true AND p.bloqueado = false";

        if (!empty(self::$codfabricante)) {
            $sql .= " AND p.codfabricante = " . $db->var2str(self::$codfabricante);
        }

        if (!empty(self::$codfamilia)) {
            $familias = self::obtenerFamiliasRelacionadas(self::$codfamilia);
            $sql .= " AND p.codfamilia IN (" . implode(',', array_map([$db, 'var2str'], $familias)) . ")";
        }

        if (self::$vendido) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM lineasfacturascli lfc
                JOIN facturascli fc ON lfc.idfactura = fc.idfactura
                WHERE lfc.referencia = v.referencia AND fc.codcliente = " . $db->var2str(self::$codcliente) . "
            )";
        }

        if (!empty(self::$query)) {
            $sql .= " AND (" . self::construirCondicionesBusqueda($db, self::$query) . ")";
        }

        $sql .= " ORDER BY " . self::obtenerOrdenSQL();

        $resultados = $db->selectLimit($sql);
        
        if (self::$vendido) {
            self::agregarUltimosPrecios($db, $resultados);
        }

        return $resultados;
    }

    protected static function obtenerAtributo(?int $idAtributo): string
    {
        if (empty($idAtributo)) {
            return '';
        }

        if (!isset(self::$idatributovalores[$idAtributo])) {
            $atributoValor = new AtributoValor();
            if ($atributoValor->loadFromCode($idAtributo)) {
                self::$idatributovalores[$idAtributo] = $atributoValor->descripcion;
            } else {
                self::$idatributovalores[$idAtributo] = '';
            }
        }

        return empty(self::$idatributovalores[$idAtributo]) ? 
            '' : ', ' . self::$idatributovalores[$idAtributo];
    }

    protected static function renderCustomerModal(Translator $i18n, string $url, User $user, ControllerPermissions $permissions): string
    {
        $clientes = self::getClientes($user, $permissions);
        $filasClientes = '';

        foreach ($clientes as $cliente) {
            $nombreMostrar = ($cliente->nombre === $cliente->razonsocial) ? 
                $cliente->nombre : 
                $cliente->nombre . ' <small>(' . $cliente->razonsocial . ')</small>';
            
            $filasClientes .= '<tr class="clickableRow" onclick="document.forms[\'salesForm\'][\'codcliente\'].value = \''
                . $cliente->codcliente . '\'; $(\'#findCustomerModal\').modal(\'hide\'); salesFormAction(\'set-customer\', \'0\'); return false;">'
                . '<td><i class="fas fa-user fa-fw"></i> ' . $nombreMostrar . '</td>'
                . '</tr>';
        }

        $parametrosAgente = $user->codagente ? '&codagente=' . $user->codagente : '';

        return '<div class="modal fade" id="findCustomerModal" tabindex="-1" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-scrollable">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fas fa-users fa-fw"></i> ' . $i18n->trans('customers') . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="Close">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="modal-body p-0">'
            . '<div class="p-3">'
            . '<div class="input-group">'
            . '<input type="text" id="findCustomerInput" class="form-control" placeholder="' . $i18n->trans('search') . '" />'
            . '<div class="input-group-append">'
            . '<button type="button" class="btn btn-primary"><i class="fas fa-search"></i></button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<table class="table table-hover mb-0">' . $filasClientes . '</table>'
            . '</div>'
            . '<div class="modal-footer bg-light">'
            . '<a href="EditCliente?return=' . urlencode($url) . $parametrosAgente . '" class="btn btn-block btn-success">'
            . '<i class="fas fa-plus fa-fw"></i> ' . $i18n->trans('new')
            . '</a>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    protected static function renderProductModal(Translator $i18n): string
    {
        return '<div class="modal fade" id="findProductModal" tabindex="-1" aria-hidden="true">'
            . '<div class="modal-dialog modal-xl">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fas fa-cubes fa-fw"></i> ' . $i18n->trans('products') . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="Close">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="form-row">'
            . '<div class="col-sm mb-2">'
            . '<div class="input-group">'
            . '<input type="text" name="fp_query" class="form-control" id="productModalInput" placeholder="' . $i18n->trans('search')
            . '" onkeyup="return salesFormActionWait(\'find-product\', \'0\', event);"/>'
            . '<div class="input-group-append">'
            . '<button class="btn btn-primary btn-spin-action" type="button" onclick="return salesFormAction(\'find-product\', \'0\');">'
            . '<i class="fas fa-search"></i></button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="col-sm mb-2">' . self::renderManufacturerFilter($i18n) . '</div>'
            . '<div class="col-sm mb-2">' . self::renderFamilyFilter($i18n) . '</div>'
            . '<div class="col-sm mb-2">' . self::renderSortFilter($i18n) . '</div>'
            . '</div>'
            . '<div class="form-row">'
            . '<div class="col-sm">'
            . '<div class="form-check">'
            . '<input type="checkbox" name="fp_vendido" value="1" class="form-check-input" id="vendido" onchange="return salesFormAction(\'find-product\', \'0\');">'
            . '<label class="form-check-label" for="vendido">' . $i18n->trans('previously-sold-to-customer') . '</label>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="table-responsive" id="findProductList">' . self::renderProductList() . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    protected static function renderSortFilter(Translator $i18n): string
    {
        return '<div class="input-group">'
            . '<div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-sort-amount-down-alt"></i></span></div>'
            . '<select name="fp_orden" class="form-control" onchange="return salesFormAction(\'find-product\', \'0\');">'
            . '<option value="">' . $i18n->trans('sort') . '</option>'
            . '<option value="">------</option>'
            . '<option value="ref_asc">' . $i18n->trans('reference') . '</option>'
            . '<option value="desc_asc">' . $i18n->trans('description') . '</option>'
            . '<option value="price_desc">' . $i18n->trans('price') . '</option>'
            . '<option value="stock_desc">' . $i18n->trans('stock') . '</option>'
            . '</select>'
            . '</div>';
    }

    protected static function agregarUltimosPrecios(DataBase $db, array &$productos): void
    {
        foreach ($productos as &$producto) {
            $sql = "SELECT l.pvpunitario FROM lineasfacturascli l
                    JOIN facturascli f ON l.idfactura = f.idfactura
                    WHERE f.codcliente = " . $db->var2str(self::$codcliente) . "
                    AND l.referencia = " . $db->var2str($producto['referencia']) . "
                    ORDER BY f.fecha DESC, l.idlinea DESC
                    LIMIT 1";
            
            $resultado = $db->selectLimit($sql, 1);
            $producto['ultimo_precio'] = !empty($resultado) ? 
                $resultado[0]['pvpunitario'] : $producto['precio'];
        }
    }

    private static function renderSubfamilias(Familia $familiaPadre, Translator $i18n, int $nivel = 1): string
    {
        $opciones = '';
        $subfamilias = $familiaPadre->getSubfamilias();
        
        foreach ($subfamilias as $subfamilia) {
            $guiones = str_repeat('-', $nivel);
            $opciones .= '<option value="' . $subfamilia->codfamilia . '">'
                . $guiones . ' ' . $subfamilia->descripcion
                . '</option>';
            
            $opciones .= self::renderSubfamilias($subfamilia, $i18n, $nivel + 1);
        }
        
        return $opciones;
    }

    private static function obtenerFamiliasRelacionadas(string $codfamilia): array
    {
        $familias = [$codfamilia];
        $familiaModel = new Familia();
        
        if ($familiaModel->loadFromCode($codfamilia)) {
            $subfamilias = $familiaModel->getSubfamilias();
            foreach ($subfamilias as $subfamilia) {
                $familias[] = $subfamilia->codfamilia;
                $familias = array_merge($familias, self::obtenerFamiliasRelacionadas($subfamilia->codfamilia));
            }
        }
        
        return array_unique($familias);
    }

    private static function construirCondicionesBusqueda(DataBase $db, string $query): string
    {
        $palabras = explode(' ', $query);
        
        if (count($palabras) === 1) {
            return "LOWER(v.codbarras) = " . $db->var2str($query) . "
                OR LOWER(v.referencia) LIKE '%" . $query . "%'
                OR LOWER(p.descripcion) LIKE '%" . $query . "%'";
        }
        
        $condiciones = "LOWER(v.referencia) LIKE '%" . $query . "%' OR (";
        foreach ($palabras as $indice => $palabra) {
            $condiciones .= ($indice > 0 ? " AND " : "") . "LOWER(p.descripcion) LIKE '%" . $palabra . "%'";
        }
        $condiciones .= ")";
        
        return $condiciones;
    }

    private static function obtenerOrdenSQL(): string
    {
        switch (self::$orden) {
            case 'desc_asc':
                return "p.descripcion ASC";
            case 'price_desc':
                return "v.precio DESC";
            case 'stock_desc':
                return "disponible DESC";
            case 'ref_asc':
            default:
                return "v.referencia ASC";
        }
    }
}