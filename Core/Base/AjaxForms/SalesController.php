<?php

namespace ERPIA\Core\Base\FormulariosAjax;

use ERPIA\Core\Base\Calculador;
use ERPIA\Core\Base\BaseDeDatos\DondeBaseDeDatos;
use ERPIA\Core\FuenteDatos\Series;
use ERPIA\Core\Lib\VistaExtendida\VistaBase;
use ERPIA\Core\Lib\VistaExtendida\TraitArchivosDoc;
use ERPIA\Core\Lib\VistaExtendida\TraitLogAuditoria;
use ERPIA\Core\Lib\VistaExtendida\ControladorPanel;
use ERPIA\Core\Modelo\Base\DocumentoVenta;
use ERPIA\Core\Modelo\Base\LineaDocumentoVenta;
use ERPIA\Core\Herramientas;
use ERPIA\Dinamic\Lib\GestorActivos;
use ERPIA\Dinamic\Modelo\Cliente;
use ERPIA\Dinamic\Modelo\AccesoRol;
use ERPIA\Dinamic\Modelo\Variante;

/**
 * Controlador abstracto para documentos de venta
 */
abstract class ControladorVentas extends ControladorPanel
{
    use TraitArchivosDoc;
    use TraitLogAuditoria;
    
    const VISTA_PRINCIPAL = 'main';
    const PLANTILLA_VISTA_PRINCIPAL = 'Tab/DocumentoVenta';
    
    private $nivelesLog = ['critical', 'error', 'info', 'notice', 'warning'];

    abstract public function obtenerClaseModelo();

    public function obtenerModelo(bool $recargar = false): DocumentoVenta
    {
        if ($recargar) {
            $this->vistas[static::VISTA_PRINCIPAL]->modelo->limpiar();
        }
        
        if ($this->vistas[static::VISTA_PRINCIPAL]->modelo->valorColumnaPrincipal()) {
            return $this->vistas[static::VISTA_PRINCIPAL]->modelo;
        }
        
        $codigo = $this->peticion->get('code');
        if (empty($codigo)) {
            $datosFormulario = $this->peticion->query->all();
            CabeceraVentasHTML::aplicar($this->vistas[static::VISTA_PRINCIPAL]->modelo, $datosFormulario, $this->usuario);
            PieVentasHTML::aplicar($this->vistas[static::VISTA_PRINCIPAL]->modelo, $datosFormulario, $this->usuario);
            return $this->vistas[static::VISTA_PRINCIPAL]->modelo;
        }
        
        $this->vistas[static::VISTA_PRINCIPAL]->modelo->cargarDesdeCodigo($codigo);
        return $this->vistas[static::VISTA_PRINCIPAL]->modelo;
    }

    /**
     * Renderiza el formulario completo de ventas
     */
    public function renderizarFormularioVentas(DocumentoVenta $modelo, array $lineas): string
    {
        $url = empty($modelo->valorColumnaPrincipal()) ? $this->url() : $modelo->url();
        return '<div id="formularioVentasCabecera">' . CabeceraVentasHTML::renderizar($modelo) . '</div>'
            . '<div id="formularioVentasLineas">' . LineasVentasHTML::renderizar($lineas, $modelo) . '</div>'
            . '<div id="formularioVentasPie">' . PieVentasHTML::renderizar($modelo) . '</div>'
            . ModalVentasHTML::renderizar($modelo, $url, $this->usuario, $this->permisos);
    }

    public function series(string $tipo = ''): array
    {
        if (empty($tipo)) {
            return Series::todas();
        }
        
        $lista = [];
        foreach (Series::todas() as $serie) {
            if ($serie->tipo == $tipo) {
                $lista[] = $serie;
            }
        }
        return $lista;
    }

    protected function accionAutocompletarProducto(): bool
    {
        $this->establecerPlantilla(false);
        $lista = [];
        $variante = new Variante();
        $consulta = (string)$this->peticion->get('term');
        $donde = [
            new DondeBaseDeDatos('p.bloqueado', 0),
            new DondeBaseDeDatos('p.sevende', 1)
        ];
        
        foreach ($variante->buscarModeloCodigo($consulta, 'referencia', $donde) as $valor) {
            $lista[] = [
                'key' => Herramientas::corregirHtml($valor->code),
                'value' => Herramientas::corregirHtml($valor->description)
            ];
        }

        if (empty($lista)) {
            $lista[] = ['key' => null, 'value' => Herramientas::idioma()->trans('no-data')];
        }
        
        $this->respuesta->setContent(json_encode($lista));
        return false;
    }

    protected function crearVistas()
    {
        $this->establecerPosicionPestanias('top');
        $this->crearVistasDoc();
        $this->crearVistaArchivosDoc();
        $this->crearVistaLogAuditoria();
    }

    protected function crearVistasDoc()
    {
        $datosPagina = $this->obtenerDatosPagina();
        $this->agregarVistaHtml(static::VISTA_PRINCIPAL, static::PLANTILLA_VISTA_PRINCIPAL, $this->obtenerClaseModelo(), $datosPagina['title'], 'fas fa-file');
        
        GestorActivos::agregarCss(RUTA_FS . '/node_modules/jquery-ui-dist/jquery-ui.min.css', 2);
        GestorActivos::agregarJs(RUTA_FS . '/node_modules/jquery-ui-dist/jquery-ui.min.js', 2);
        
        CabeceraVentasHTML::recursos();
        LineasVentasHTML::recursos();
        PieVentasHTML::recursos();
    }

    protected function accionEliminarDoc(): bool
    {
        $this->establecerPlantilla(false);
        if ($this->permisos->permitirEliminar === false) {
            Herramientas::log()->warning('not-allowed-delete');
            $this->enviarJsonConLogs(['ok' => false]);
            return false;
        }
        
        $modelo = $this->obtenerModelo();
        if ($modelo->eliminar() === false) {
            $this->enviarJsonConLogs(['ok' => false]);
            return false;
        }
        
        $this->enviarJsonConLogs(['ok' => true, 'newurl' => $modelo->url('list')]);
        return false;
    }

    /**
     * Ejecuta acciones previas
     */
    protected function ejecutarAccionPrevia($accion)
    {
        switch ($accion) {
            case 'add-file':
                return $this->accionAgregarArchivo();
            case 'autocomplete-product':
                return $this->accionAutocompletarProducto();
            case 'add-product':
            case 'fast-line':
            case 'fast-product':
            case 'new-line':
            case 'recalculate':
            case 'rm-line':
            case 'set-customer':
                return $this->accionRecalcular(true);
            case 'delete-doc':
                return $this->accionEliminarDoc();
            case 'delete-file':
                return $this->accionEliminarArchivo();
            case 'edit-file':
                return $this->accionEditarArchivo();
            case 'find-customer':
                return $this->accionBuscarCliente();
            case 'find-product':
                return $this->accionBuscarProducto();
            case 'recalculate-line':
                return $this->accionRecalcular(false);
            case 'save-doc':
                $this->accionGuardarDoc();
                return false;
            case 'save-paid':
                return $this->accionGuardarPagado();
            case 'save-status':
                return $this->accionGuardarEstado();
            case 'unlink-file':
                return $this->accionDesvincularArchivo();
        }
        
        return parent::ejecutarAccionPrevia($accion);
    }

    protected function accionExportar()
    {
        $this->establecerPlantilla(false);
        $idiomaSujeto = $this->vistas[static::VISTA_PRINCIPAL]->modelo->obtenerSujeto()->langcode;
        $idiomaPeticion = $this->peticion->request->get('langcode');
        $codigoIdioma = $idiomaPeticion ?? $idiomaSujeto ?? '';
        
        $this->gestorExportacion->nuevoDoc(
            $this->peticion->get('option', ''),
            $this->titulo,
            (int)$this->peticion->request->get('idformat', ''),
            $codigoIdioma
        );
        
        $this->gestorExportacion->agregarPaginaDocComercial($this->vistas[static::VISTA_PRINCIPAL]->modelo);
        $this->gestorExportacion->mostrar($this->respuesta);
    }

    protected function accionBuscarCliente(): bool
    {
        $this->establecerPlantilla(false);
        $mostrarTodos = false;
        
        foreach (AccesoRol::todosDesdeUsuario($this->usuario->nick, 'EditCliente') as $acceso) {
            if ($acceso->onlyownerdata === false) {
                $mostrarTodos = true;
            }
        }
        
        $donde = [];
        if ($this->permisos->onlyOwnerData && !$mostrarTodos) {
            $donde[] = new DondeBaseDeDatos('codagente', $this->usuario->codagente);
            $donde[] = new DondeBaseDeDatos('codagente', null, 'IS NOT');
        }
        
        $lista = [];
        $cliente = new Cliente();
        $termino = $this->peticion->get('term');
        
        foreach ($cliente->buscarModeloCodigo($termino, '', $donde) as $item) {
            $lista[$item->code] = $item->code . ' | ' . Herramientas::corregirHtml($item->description);
        }
        
        $this->respuesta->setContent(json_encode($lista));
        return false;
    }

    protected function accionBuscarProducto(): bool
    {
        $this->establecerPlantilla(false);
        $modelo = $this->obtenerModelo();
        $datosFormulario = json_decode($this->peticion->request->get('data'), true);
        
        CabeceraVentasHTML::aplicar($modelo, $datosFormulario, $this->usuario);
        PieVentasHTML::aplicar($modelo, $datosFormulario, $this->usuario);
        ModalVentasHTML::aplicar($modelo, $datosFormulario);
        
        $contenido = [
            'header' => '',
            'lines' => '',
            'linesMap' => [],
            'footer' => '',
            'products' => ModalVentasHTML::renderizarListaProductos()
        ];
        
        $this->enviarJsonConLogs($contenido);
        return false;
    }

    /**
     * Carga datos en las vistas
     */
    protected function cargarDatos($nombreVista, $vista)
    {
        $codigo = $this->peticion->get('code');
        
        switch ($nombreVista) {
            case 'docfiles':
                $this->cargarDatosArchivosDoc($vista, $this->obtenerClaseModelo(), $codigo);
                break;
            case 'ListLogMessage':
                $this->cargarDatosLogAuditoria($vista, $this->obtenerClaseModelo(), $codigo);
                break;
            case static::VISTA_PRINCIPAL:
                if (empty($codigo)) {
                    $this->obtenerModelo(true);
                    break;
                }
                
                $vista->cargarDatos($codigo);
                $accion = $this->peticion->request->get('action', '');
                
                if ($accion === '' && empty($vista->modelo->valorColumnaPrincipal())) {
                    Herramientas::log()->warning('record-not-found');
                    break;
                }
                
                $this->titulo .= ' ' . $vista->modelo->descripcionPrincipal();
                $vista->settings['btnPrint'] = true;
                
                $this->agregarBoton($nombreVista, [
                    'action' => 'CopyModel?model=' . $this->obtenerClaseModelo() . '&code=' . $vista->modelo->valorColumnaPrincipal(),
                    'icon' => 'fas fa-cut',
                    'label' => 'copy',
                    'type' => 'link'
                ]);
                break;
        }
    }

    protected function accionRecalcular(bool $renderizarLineas): bool
    {
        $this->establecerPlantilla(false);
        $modelo = $this->obtenerModelo();
        $lineas = $modelo->obtenerLineas();
        $datosFormulario = json_decode($this->peticion->request->get('data'), true);
        
        CabeceraVentasHTML::aplicar($modelo, $datosFormulario, $this->usuario);
        PieVentasHTML::aplicar($modelo, $datosFormulario, $this->usuario);
        LineasVentasHTML::aplicar($modelo, $lineas, $datosFormulario);
        Calculador::calcular($modelo, $lineas, false);
        
        $contenido = [
            'header' => CabeceraVentasHTML::renderizar($modelo),
            'lines' => $renderizarLineas ? LineasVentasHTML::renderizar($lineas, $modelo) : '',
            'linesMap' => $renderizarLineas ? [] : LineasVentasHTML::mapear($lineas, $modelo),
            'footer' => PieVentasHTML::renderizar($modelo),
            'products' => '',
        ];
        
        $this->enviarJsonConLogs($contenido);
        return false;
    }

    protected function accionGuardarDoc(): bool
    {
        $this->establecerPlantilla(false);
        
        if ($this->permisos->permitirActualizar === false) {
            Herramientas::log()->warning('not-allowed-modify');
            $this->enviarJsonConLogs(['ok' => false]);
            return false;
        }
        
        $this->baseDeDatos->iniciarTransaccion();
        $modelo = $this->obtenerModelo();
        $datosFormulario = json_decode($this->peticion->request->get('data'), true);
        
        CabeceraVentasHTML::aplicar($modelo, $datosFormulario, $this->usuario);
        PieVentasHTML::aplicar($modelo, $datosFormulario, $this->usuario);

        if ($modelo->guardar() === false) {
            $this->enviarJsonConLogs(['ok' => false]);
            $this->baseDeDatos->revertirTransaccion();
            return false;
        }
        
        $lineas = $modelo->obtenerLineas();
        LineasVentasHTML::aplicar($modelo, $lineas, $datosFormulario);
        Calculador::calcular($modelo, $lineas, false);
        
        foreach ($lineas as $linea) {
            if ($linea->guardar() === false) {
                $this->enviarJsonConLogs(['ok' => false]);
                $this->baseDeDatos->revertirTransaccion();
                return false;
            }
        }
        
        foreach ($modelo->obtenerLineas() as $lineaAntigua) {
            if (in_array($lineaAntigua->idlinea, LineasVentasHTML::obtenerLineasEliminadas()) && $lineaAntigua->eliminar() === false) {
                $this->enviarJsonConLogs(['ok' => false]);
                $this->baseDeDatos->revertirTransaccion();
                return false;
            }
        }
        
        $lineas = $modelo->obtenerLineas();
        if (Calculador::calcular($modelo, $lineas, true) === false) {
            $this->enviarJsonConLogs(['ok' => false]);
            $this->baseDeDatos->revertirTransaccion();
            return false;
        }
        
        $this->enviarJsonConLogs(['ok' => true, 'newurl' => $modelo->url() . '&action=save-ok']);
        $this->baseDeDatos->confirmarTransaccion();
        return true;
    }

    protected function accionGuardarPagado(): bool
    {
        $this->establecerPlantilla(false);
        
        if ($this->permisos->permitirActualizar === false) {
            Herramientas::log()->warning('not-allowed-modify');
            $this->enviarJsonConLogs(['ok' => false]);
            return false;
        }
        
        if ($this->obtenerModelo()->editable && $this->accionGuardarDoc() === false) {
            return false;
        }
        
        $modelo = $this->obtenerModelo();
        
        if (empty($modelo->total) && property_exists($modelo, 'pagada')) {
            $modelo->pagada = (bool)$this->peticion->request->get('selectedLine');
            $modelo->guardar();
            $this->enviarJsonConLogs(['ok' => true, 'newurl' => $modelo->url() . '&action=save-ok']);
            return false;
        }
        
        $recibos = $modelo->obtenerRecibos();
        if (empty($recibos)) {
            Herramientas::log()->warning('invoice-has-no-receipts');
            $this->enviarJsonConLogs(['ok' => false]);
            return false;
        }
        
        $datosFormulario = json_decode($this->peticion->request->get('data'), true);
        
        foreach ($recibos as $recibo) {
            $recibo->nick = $this->usuario->nick;
            if ($recibo->pagado == false) {
                $recibo->fechapago = $datosFormulario['fechapagorecibo'] ?? Herramientas::fecha();
                $recibo->codpago = $modelo->codpago;
            }
            $recibo->pagado = (bool)$this->peticion->request->get('selectedLine');
            if ($recibo->guardar() === false) {
                $this->enviarJsonConLogs(['ok' => false]);
                return false;
            }
        }

        $this->enviarJsonConLogs(['ok' => true, 'newurl' => $modelo->url() . '&action=save-ok']);
        return false;
    }

    protected function accionGuardarEstado(): bool
    {
        $this->establecerPlantilla(false);
        
        if ($this->permisos->permitirActualizar === false) {
            Herramientas::log()->warning('not-allowed-modify');
            $this->enviarJsonConLogs(['ok' => false]);
            return false;
        }
        
        if ($this->obtenerModelo()->editable && $this->accionGuardarDoc() === false) {
            return false;
        }
        
        $modelo = $this->obtenerModelo();
        $modelo->idestado = (int)$this->peticion->request->get('selectedLine');
        
        if ($modelo->guardar() === false) {
            $this->enviarJsonConLogs(['ok' => false]);
            return false;
        }
        
        $this->enviarJsonConLogs(['ok' => true, 'newurl' => $modelo->url() . '&action=save-ok']);
        return false;
    }

    private function enviarJsonConLogs(array $datos): void
    {
        $datos['messages'] = [];
        foreach (Herramientas::log()::read('', $this->nivelesLog) as $mensaje) {
            if ($mensaje['channel'] != 'audit') {
                $datos['messages'][] = $mensaje;
            }
        }
        $this->respuesta->setContent(json_encode($datos));
    }
}