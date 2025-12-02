<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\Controller;
use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Cache;
use ERPIA\Core\Http;
use ERPIA\Core\Internal\Forja;
use ERPIA\Core\Model\Base\BusinessDocument;
use ERPIA\Core\Plugins;
use ERPIA\Core\Response;
use ERPIA\Core\Telemetry;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\AlbaranCliente;
use ERPIA\Dinamic\Model\Cliente;
use ERPIA\Dinamic\Model\Contacto;
use ERPIA\Dinamic\Model\FacturaCliente;
use ERPIA\Dinamic\Model\PedidoCliente;
use ERPIA\Dinamic\Model\PresupuestoCliente;
use ERPIA\Dinamic\Model\Producto;
use ERPIA\Dinamic\Model\ReciboCliente;
use ERPIA\Dinamic\Model\Stock;
use ERPIA\Dinamic\Model\TotalModel;
use ERPIA\Dinamic\Model\User;

class Dashboard extends Controller
{
    public $enlacesCreacion = [];
    public $stockBajo = [];
    public $noticias = [];
    public $enlacesRecientes = [];
    public $recibos = [];
    public $registrado = false;
    public $secciones = [];
    public $estadisticas = [];
    public $actualizado = false;

    public function obtenerDatosPagina(): array
    {
        $datos = parent::obtenerDatosPagina();
        $datos['menu'] = 'informes';
        $datos['titulo'] = 'panel_control';
        $datos['icono'] = 'fas fa-chart-line';
        return $datos;
    }

    public function nucleoPrivado(&$respuesta, $usuario, $permisos): void
    {
        parent::nucleoPrivado($respuesta, $usuario, $permisos);
        $this->titulo = Tools::trans('panel-para', ['%empresa%' => $this->empresa->nombrecorto]);
        $this->cargarExtensiones();
        $this->registrado = Telemetry::iniciar()->listo();
        $this->actualizado = Forja::puedeActualizarNucleo() === false;
    }

    public function mostrarAdvertenciaBackup(): bool
    {
        if ($_SERVER['REMOTE_ADDR'] == 'localhost' ||
            $_SERVER['REMOTE_ADDR'] == '::1' ||
            substr($_SERVER['REMOTE_ADDR'], 0, 4) == '192.' ||
            substr($_SERVER['REMOTE_ADDR'], 0, 4) == '172.') {
            return !Plugins::estaHabilitado('Backup');
        }
        return false;
    }

    private function obtenerMesEstadisticas(int $mesAnterior): string
    {
        $primerDia = date('01-m-Y');
        $fecha = $mesAnterior > 0 ? date('01-m-Y', strtotime($primerDia . ' -' . $mesAnterior . ' month')) : $primerDia;
        return strtolower(date('F', strtotime($fecha)));
    }

    private function obtenerFiltroEstadisticas(string $campo, int $mesAnterior): array
    {
        $primerDia = date('01-m-Y');
        $desde = $mesAnterior > 0 ? date('01-m-Y', strtotime($primerDia . ' -' . $mesAnterior . ' month')) : $primerDia;
        $hasta = date('01-m-Y', strtotime($desde . ' +1 month'));
        return [
            new DataBaseWhere($campo, $desde, '>='),
            new DataBaseWhere($campo, $hasta, '<'),
        ];
    }

    private function cargarEnlacesCreacion(): void
    {
        $this->enlacesCreacion['EditarProducto'] = 'producto';
        $this->enlacesCreacion['EditarCliente'] = 'cliente';
        $this->enlacesCreacion['EditarContacto'] = 'contacto';
        $this->enlacesCreacion['EditarFacturaCliente'] = 'factura-cliente';
        $this->enlacesCreacion['EditarAlbaranCliente'] = 'albaran-cliente';
        $this->enlacesCreacion['EditarPedidoCliente'] = 'pedido-cliente';
        $this->enlacesCreacion['EditarPresupuestoCliente'] = 'presupuesto-cliente';
        $this->pipe('cargarEnlacesCreacion');
    }

    private function cargarExtensiones(): void
    {
        $this->cargarEnlacesCreacion();
        $this->cargarEnlacesRecientes();
        $this->cargarEstadisticas();
        $this->cargarSeccionStockBajo();
        $this->cargarSeccionRecibos();
        $this->cargarNoticias();
        $this->pipe('cargarExtensiones');
    }

    private function cargarSeccionStockBajo(): void
    {
        if ($this->baseDatos->existeTabla('stocks') === false) {
            return;
        }
        $encontrado = false;
        $consulta = 'SELECT * FROM stocks WHERE stockmin > 0 AND disponible < stockmin;';
        foreach ($this->baseDatos->seleccionar($consulta) as $fila) {
            $this->stockBajo[] = new Stock($fila);
            $encontrado = true;
        }
        if ($encontrado) {
            $this->secciones[] = 'stock-bajo';
        }
    }

    private function cargarNoticias(): void
    {
        $this->noticias = Cache::recordar('panel-noticias', function () {
            return Http::obtener('https://erpia.com/noticias/ultimas?formato=json')
                ->establecerTimeout(5)
                ->json() ?? [];
        });
    }

    private function cargarEnlacesRecientes(): void
    {
        $this->establecerEnlacesRecientesDocumento(new FacturaCliente(), 'factura');
        $this->establecerEnlacesRecientesDocumento(new AlbaranCliente(), 'albaran');
        $this->establecerEnlacesRecientesDocumento(new PedidoCliente(), 'pedido');
        $this->establecerEnlacesRecientesDocumento(new PresupuestoCliente(), 'presupuesto');

        $fechaMinima = Tools::fecha('-2 days');
        $fechaHoraMinima = Tools::fechaHora('-2 days');

        $filtroCliente = [new DataBaseWhere('fechaalta', $fechaMinima, '>=')];
        foreach (Cliente::todo($filtroCliente, ['fechaalta' => 'DESC'], 0, 3) as $cliente) {
            $this->enlacesRecientes[] = [
                'tipo' => 'cliente',
                'url' => $cliente->url(),
                'nombre' => $cliente->nombre,
                'fecha' => $cliente->fechaalta,
            ];
        }

        $filtroContacto = [new DataBaseWhere('fechaalta', $fechaMinima, '>=')];
        foreach (Contacto::todo($filtroContacto, ['fechaalta' => 'DESC'], 0, 3) as $contacto) {
            $this->enlacesRecientes[] = [
                'tipo' => 'contacto',
                'url' => $contacto->url(),
                'nombre' => $contacto->nombreCompleto(),
                'fecha' => $contacto->fechaalta,
            ];
        }

        $filtroProducto = [new DataBaseWhere('actualizado', $fechaHoraMinima, '>=')];
        foreach (Producto::todo($filtroProducto, ['actualizado' => 'DESC'], 0, 3) as $producto) {
            $this->enlacesRecientes[] = [
                'tipo' => 'producto',
                'url' => $producto->url(),
                'nombre' => $producto->referencia,
                'fecha' => $producto->actualizado,
            ];
        }

        $this->pipe('cargarEnlacesRecientes');
    }

    private function cargarSeccionRecibos(): void
    {
        $filtro = [
            new DataBaseWhere('pagado', false),
            new DataBaseWhere('vencimiento', Tools::fecha(), '<'),
            new DataBaseWhere('vencimiento', date('Y-m-d', strtotime('-1 year')), '>'),
        ];
        $this->recibos = ReciboCliente::todo($filtro, ['vencimiento' => 'DESC']);
        if (count($this->recibos) > 0) {
            $this->secciones[] = 'recibos';
        }
    }

    private function cargarEstadisticas(): void
    {
        $modeloTotal = new TotalModel();

        $this->estadisticas['compras'] = [
            $this->obtenerMesEstadisticas(0) => $modeloTotal->sumar('facturasprov', 'neto', $this->obtenerFiltroEstadisticas('fecha', 0)),
            $this->obtenerMesEstadisticas(1) => $modeloTotal->sumar('facturasprov', 'neto', $this->obtenerFiltroEstadisticas('fecha', 1)),
            $this->obtenerMesEstadisticas(2) => $modeloTotal->sumar('facturasprov', 'neto', $this->obtenerFiltroEstadisticas('fecha', 2)),
        ];

        $this->estadisticas['ventas'] = [
            $this->obtenerMesEstadisticas(0) => $modeloTotal->sumar('facturascli', 'neto', $this->obtenerFiltroEstadisticas('fecha', 0)),
            $this->obtenerMesEstadisticas(1) => $modeloTotal->sumar('facturascli', 'neto', $this->obtenerFiltroEstadisticas('fecha', 1)),
            $this->obtenerMesEstadisticas(2) => $modeloTotal->sumar('facturascli', 'neto', $this->obtenerFiltroEstadisticas('fecha', 2)),
        ];

        foreach ([0, 1, 2] as $numero) {
            $filtro = $this->obtenerFiltroEstadisticas('fecha', $numero);
            $this->estadisticas['impuestos'][$this->obtenerMesEstadisticas($numero)] = $modeloTotal->sumar('facturascli', 'totaliva', $filtro)
                + $modeloTotal->sumar('facturascli', 'totalrecargo', $filtro)
                - $modeloTotal->sumar('facturasprov', 'totaliva', $filtro)
                - $modeloTotal->sumar('facturasprov', 'totalrecargo', $filtro);
        }

        $modeloCliente = new Cliente();
        $this->estadisticas['nuevos-clientes'] = [
            $this->obtenerMesEstadisticas(0) => $modeloCliente->contar($this->obtenerFiltroEstadisticas('fechaalta', 0)),
            $this->obtenerMesEstadisticas(1) => $modeloCliente->contar($this->obtenerFiltroEstadisticas('fechaalta', 1)),
            $this->obtenerMesEstadisticas(2) => $modeloCliente->contar($this->obtenerFiltroEstadisticas('fechaalta', 2)),
        ];
    }

    private function establecerEnlacesRecientesDocumento($modelo, $etiqueta): void
    {
        $fechaMinima = Tools::fecha('-2 days');
        $filtro = [
            new DataBaseWhere('fecha', $fechaMinima, '>='),
            new DataBaseWhere('nick', $this->usuario->nick),
        ];
        foreach ($modelo->todo($filtro, [$modelo->columnaPrincipal() => 'DESC'], 0, 3) as $documento) {
            $this->enlacesRecientes[] = [
                'tipo' => $etiqueta,
                'url' => $documento->url(),
                'nombre' => $documento->codigo,
                'fecha' => $documento->fecha,
            ];
        }
    }
}