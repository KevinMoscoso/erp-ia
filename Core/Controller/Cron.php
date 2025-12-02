<?php

namespace ERPIA\Core\Controller;

use Exception;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Cache;
use ERPIA\Core\Contract\ControllerInterface;
use ERPIA\Core\Kernel;
use ERPIA\Core\Plugins;
use ERPIA\Core\Tools;
use ERPIA\Core\Where;
use ERPIA\Core\WorkQueue;
use ERPIA\Dinamic\Model\AlbaranCliente;
use ERPIA\Dinamic\Model\AlbaranProveedor;
use ERPIA\Dinamic\Model\AttachedFileRelation;
use ERPIA\Dinamic\Model\CronJob;
use ERPIA\Dinamic\Model\Fabricante;
use ERPIA\Dinamic\Model\FacturaCliente;
use ERPIA\Dinamic\Model\FacturaProveedor;
use ERPIA\Dinamic\Model\Familia;
use ERPIA\Dinamic\Model\LogMessage;
use ERPIA\Dinamic\Model\PedidoCliente;
use ERPIA\Dinamic\Model\PedidoProveedor;
use ERPIA\Dinamic\Model\PresupuestoCliente;
use ERPIA\Dinamic\Model\PresupuestoProveedor;
use ERPIA\Dinamic\Model\Producto;
use ERPIA\Dinamic\Model\ReciboCliente;
use ERPIA\Dinamic\Model\ReciboProveedor;
use ERPIA\Dinamic\Model\WorkEvent;

class Cron implements ControllerInterface
{
    public function __construct(string $className, string $url = '')
    {
    }

    public function obtenerDatosPagina(): array
    {
        return [];
    }

    public function ejecutar(): void
    {
        header('Content-Type: text/plain');
        $this->mostrarLogo();
        Tools::registro('cron')->noticia('iniciando-cron');
        echo PHP_EOL . PHP_EOL . Tools::trans('iniciando-cron');
        ob_flush();
        $this->ejecutarPlugins();
        $this->ejecutarTrabajosNucleo();
        $this->ejecutarColaTrabajos();
        $niveles = ['critico', 'error', 'info', 'noticia', 'advertencia'];
        foreach (Tools::registro()::leer('', $niveles) as $mensaje) {
            if (!in_array($mensaje['canal'], ['master', 'database'])) {
                continue;
            }
            echo PHP_EOL . $mensaje['mensaje'];
            ob_flush();
        }
        Cache::expirados();
        $contexto = [
            '%tiempo%' => Kernel::obtenerTiempoEjecucion(3),
            '%memoria%' => $this->obtenerTamanoMemoria(memory_get_peak_usage())
        ];
        echo PHP_EOL . PHP_EOL . Tools::trans('cron-finalizado', $contexto) . PHP_EOL . PHP_EOL;
        Tools::registro()->noticia('cron-finalizado', $contexto);
    }

    private function mostrarLogo(): void
    {
        if (PHP_SAPI === 'cli') {
            echo <<<END

 ______ _____  _____ _____          
|  ____|  __ \|  __ \_   _|   /\    
| |__  | |__) | |__) || |    /  \   
|  __| |  _  /|  ___/ | |   / /\ \  
| |____| | \ \| |    _| |_ / ____ \ 
|______|_|  \_\_|   |_____/_/    \_\
END;
        }
    }

    private function obtenerTamanoMemoria(int $tamano): string
    {
        $unidad = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        return round($tamano / pow(1024, ($i = floor(log($tamano, 1024)))), 2) . $unidad[$i];
    }

    private function trabajo(string $nombre): CronJob
    {
        $trabajo = new CronJob();
        $where = [
            Where::eq('nombre_trabajo', $nombre),
            Where::isNull('nombre_plugin')
        ];
        if ($trabajo->cargarWhere($where) === false) {
            $trabajo->nombre_trabajo = $nombre;
        }
        return $trabajo;
    }

    protected function eliminarRegistrosAntiguos(): void
    {
        $diasMaximos = Tools::configuracion('default', 'dias_retener_log', 90);
        if ($diasMaximos <= 0) {
            return;
        }
        $fechaMinima = Tools::fechaHora('-' . $diasMaximos . ' days');
        echo PHP_EOL . PHP_EOL . Tools::trans('eliminando-logs-hasta', ['%fecha%' => $fechaMinima]) . ' ... ';
        ob_flush();
        $consulta = LogMessage::tabla()
            ->whereNotEq('canal', 'auditoria')
            ->whereLt('tiempo', $fechaMinima);
        if ($consulta->eliminar() === false) {
            Tools::registro('cron')->advertencia('error-eliminar-logs-antiguos');
            return;
        }
        Tools::registro('cron')->noticia('logs-antiguos-eliminados');
    }

    protected function eliminarEventosTrabajoAntiguos(): void
    {
        $diasMaximos = Tools::configuracion('default', 'dias_retener_log', 90);
        if ($diasMaximos <= 0) {
            return;
        }
        $fechaMinima = Tools::fechaHora('-' . $diasMaximos . ' days');
        $consulta = WorkEvent::tabla()
            ->whereEq('completado', true)
            ->whereLt('fecha_creacion', $fechaMinima);
        if ($consulta->eliminar() === false) {
            Tools::registro('cron')->advertencia('error-eliminar-eventos-antiguos');
            return;
        }
        Tools::registro('cron')->noticia('eventos-antiguos-eliminados');
    }

    protected function ejecutarTrabajosNucleo(): void
    {
        $this->trabajo('actualizar-relaciones-adjuntos')
            ->cadaDiaALas(0)
            ->ejecutar(function () {
                $this->actualizarRelacionesAdjuntas();
            });
        $this->trabajo('actualizar-familias')
            ->cadaDiaALas(1)
            ->ejecutar(function () {
                $this->actualizarFamilias();
            });
        $this->trabajo('actualizar-fabricantes')
            ->cadaDiaALas(2)
            ->ejecutar(function () {
                $this->actualizarFabricantes();
            });
        $this->trabajo('eliminar-logs-antiguos')
            ->cadaDiaALas(3)
            ->ejecutar(function () {
                $this->eliminarRegistrosAntiguos();
                $this->eliminarEventosTrabajoAntiguos();
            });
        $this->trabajo('actualizar-recibos')
            ->cadaDiaALas(4)
            ->ejecutar(function () {
                $this->actualizarRecibos();
            });
    }

    protected function ejecutarPlugins(): void
    {
        foreach (Plugins::habilitados() as $nombrePlugin) {
            $claseCron = '\\ERPIA\\Plugins\\' . $nombrePlugin . '\\Cron';
            if (class_exists($claseCron) === false) {
                continue;
            }
            echo PHP_EOL . Tools::trans('ejecutando-cron-plugin', ['%plugin%' => $nombrePlugin]) . ' ... ';
            Tools::registro('cron')->noticia('ejecutando-cron-plugin', ['%plugin%' => $nombrePlugin]);
            try {
                $cron = new $claseCron($nombrePlugin);
                $cron->ejecutar();
            } catch (Exception $excepcion) {
                echo $excepcion->getMessage() . PHP_EOL;
                Tools::registro('cron')->error($excepcion->getMessage());
            }
            ob_flush();
            if (PHP_SAPI != 'cli' && Kernel::obtenerTiempoEjecucion() > 20) {
                echo PHP_EOL . PHP_EOL . Tools::trans('tiempo-excedido-cron');
                break;
            }
        }
    }

    protected function ejecutarColaTrabajos(): void
    {
        echo PHP_EOL . PHP_EOL . Tools::trans('procesando-cola-trabajos') . ' ... ';
        ob_flush();
        $maximo = 1000;
        while ($maximo > 0) {
            if (WorkQueue::ejecutar() === false) {
                break;
            }
            --$maximo;
            if (PHP_SAPI != 'cli' && Kernel::obtenerTiempoEjecucion() > 25) {
                echo PHP_EOL . PHP_EOL . Tools::trans('tiempo-excedido-cron');
                return;
            }
        }
    }

    protected function actualizarRelacionesAdjuntas(): void
    {
        echo PHP_EOL . PHP_EOL . Tools::trans('actualizando-relaciones-adjuntos') . ' ... ';
        ob_flush();
        $modeloRelacion = new AttachedFileRelation();
        if ($modeloRelacion->contar() === 0) {
            return;
        }
        $modelos = [
            new AlbaranCliente(), new FacturaCliente(), new PedidoCliente(), new PresupuestoCliente(),
            new AlbaranProveedor(), new FacturaProveedor(), new PedidoProveedor(), new PresupuestoProveedor()
        ];
        shuffle($modelos);
        echo $modelos[0]->nombreClaseModelo();
        ob_flush();
        $limite = 100;
        $desplazamiento = 0;
        $orden = ['codigo' => 'ASC'];
        $documentos = $modelos[0]->todo([], $orden, 0, $limite);
        while (!empty($documentos)) {
            foreach ($documentos as $doc) {
                $where = [new DataBaseWhere('modelo', $doc->nombreClaseModelo())];
                $where[] = is_numeric($doc->id()) ?
                    new DataBaseWhere('modeloid|modelocodigo', $doc->id()) :
                    new DataBaseWhere('modelocodigo', $doc->id());
                $numero = $modeloRelacion->contar($where);
                if ($numero == $doc->numdocs) {
                    continue;
                }
                $doc->numdocs = $numero;
                if ($doc->guardar() === false) {
                    Tools::registro('cron')->error('error-guardar-registro', [
                        '%modelo%' => $doc->nombreClaseModelo(),
                        '%id%' => $doc->id()
                    ]);
                    break;
                }
            }
            $desplazamiento += $limite;
            $documentos = $modelos[0]->todo([], $orden, $desplazamiento, $limite);
        }
    }

    protected function actualizarFamilias(): void
    {
        echo PHP_EOL . PHP_EOL . Tools::trans('actualizando-familias') . ' ... ';
        ob_flush();
        $producto = new Producto();
        foreach (Familia::todo([], [], 0, 0) as $familia) {
            $total = $producto->contar([new DataBaseWhere('codfamilia', $familia->codfamilia)]);
            if ($familia->numproductos == $total) {
                continue;
            }
            $familia->numproductos = $total;
            $familia->guardar();
        }
    }

    protected function actualizarFabricantes(): void
    {
        echo PHP_EOL . PHP_EOL . Tools::trans('actualizando-fabricantes') . ' ... ';
        ob_flush();
        $producto = new Producto();
        foreach (Fabricante::todo([], [], 0, 0) as $fabricante) {
            $total = $producto->contar([new DataBaseWhere('codfabricante', $fabricante->codfabricante)]);
            if ($fabricante->numproductos == $total) {
                continue;
            }
            $fabricante->numproductos = $total;
            $fabricante->guardar();
        }
    }

    protected function actualizarRecibos(): void
    {
        echo PHP_EOL . PHP_EOL . Tools::trans('actualizando-recibos') . ' ... ';
        ob_flush();
        $where = [
            new DataBaseWhere('pagado', false),
            new DataBaseWhere('vencimiento', Tools::fecha(), '<')
        ];
        foreach (ReciboProveedor::todo($where, [], 0, 0) as $recibo) {
            $factura = $recibo->obtenerFactura();
            if ($recibo->codigofactura != $factura->codigo) {
                $recibo->codigofactura = $factura->codigo;
            }
            $recibo->guardar();
        }
        foreach (ReciboCliente::todo($where, [], 0, 0) as $recibo) {
            $factura = $recibo->obtenerFactura();
            if ($recibo->codigofactura != $factura->codigo) {
                $recibo->codigofactura = $factura->codigo;
            }
            $recibo->guardar();
        }
    }
}