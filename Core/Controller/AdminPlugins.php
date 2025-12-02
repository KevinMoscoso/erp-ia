<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\Controller;
use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Cache;
use ERPIA\Core\Internal\Forja;
use ERPIA\Core\Plugins;
use ERPIA\Core\Response;
use ERPIA\Core\Telemetry;
use ERPIA\Core\Tools;
use ERPIA\Core\UploadedFile;
use ERPIA\Dinamic\Model\User;

class AdminPlugins extends Controller
{
    public $listaPlugins = [];
    public $listaPluginsRemotos = [];
    public $registrado = false;
    public $actualizado = false;

    public function obtenerTamanoMaximoSubida(): float
    {
        return UploadedFile::obtenerTamanoMaximoArchivo() / 1024 / 1024;
    }

    public function obtenerDatosPagina(): array
    {
        $datos = parent::obtenerDatosPagina();
        $datos['menu'] = 'administracion';
        $datos['titulo'] = 'plugins';
        $datos['icono'] = 'fas fa-plug';
        return $datos;
    }

    public function nucleoPrivado(&$respuesta, $usuario, $permisos)
    {
        parent::nucleoPrivado($respuesta, $usuario, $permisos);

        $accion = $this->solicitud->entradaOConsulta('accion', '');
        switch ($accion) {
            case 'desactivar':
                $this->accionDesactivarPlugin();
                break;
            case 'activar':
                $this->accionActivarPlugin();
                break;
            case 'reconstruir':
                $this->accionReconstruir();
                break;
            case 'eliminar':
                $this->accionEliminarPlugin();
                break;
            case 'subir':
                $this->accionSubirPlugin();
                break;
            default:
                $this->extraerArchivosZipPlugins();
                if (ERPIA_DEBUG) {
                    Plugins::desplegar(true, true);
                    Cache::limpiar();
                }
                break;
        }

        $this->listaPlugins = Plugins::listar();
        $this->cargarListaPluginsRemotos();

        $telemetria = new Telemetry();
        $this->registrado = $telemetria->listo();

        $this->actualizado = Forja::puedeActualizarNucleo() === false;
    }

    private function accionDesactivarPlugin(): void
    {
        if ($this->permisos->permitirActualizar === false) {
            Tools::registro()->advertencia('no-permitido-modificar');
            return;
        } elseif ($this->validarTokenFormulario() === false) {
            return;
        }

        $nombrePlugin = $this->solicitud->consultaOEntrada('plugin', '');
        Plugins::desactivar($nombrePlugin);
        Cache::limpiar();
    }

    private function accionActivarPlugin(): void
    {
        if ($this->permisos->permitirActualizar === false) {
            Tools::registro()->advertencia('no-permitido-modificar');
            return;
        } elseif ($this->validarTokenFormulario() === false) {
            return;
        }

        $nombrePlugin = $this->solicitud->consultaOEntrada('plugin', '');
        Plugins::activar($nombrePlugin);
        Cache::limpiar();
    }

    private function extraerArchivosZipPlugins(): void
    {
        $exito = false;
        foreach (Tools::explorarCarpeta(Plugins::carpeta()) as $nombreArchivoZip) {
            if (pathinfo($nombreArchivoZip, PATHINFO_EXTENSION) !== 'zip') {
                continue;
            }

            $rutaZip = Plugins::carpeta() . DIRECTORY_SEPARATOR . $nombreArchivoZip;
            if (Plugins::agregar($rutaZip, $nombreArchivoZip)) {
                $exito = true;
                unlink($rutaZip);
            }
        }

        if ($exito) {
            Tools::registro()->noticia('recargando');
            $this->redirigir($this->url(), 3);
        }
    }

    private function cargarListaPluginsRemotos(): void
    {
        if (Tools::config('deshabilitar_agregar_plugins', false)) {
            return;
        }

        $pluginsInstalados = Plugins::listar();
        foreach (Forja::plugins() as $item) {
            foreach ($pluginsInstalados as $plugin) {
                if ($plugin->nombre == $item['nombre']) {
                    continue 2;
                }
            }
            $this->listaPluginsRemotos[] = $item;
        }
    }

    private function accionReconstruir(): void
    {
        if ($this->permisos->permitirActualizar === false) {
            Tools::registro()->advertencia('no-permitido-actualizar');
            return;
        } elseif ($this->validarTokenFormulario() === false) {
            return;
        }

        Plugins::desplegar(true, true);
        Cache::limpiar();
        Tools::registro()->noticia('reconstruccion-completada');
    }

    private function accionEliminarPlugin(): void
    {
        if ($this->permisos->permitirEliminar === false) {
            Tools::registro()->advertencia('no-permitido-eliminar');
            return;
        } elseif ($this->validarTokenFormulario() === false) {
            return;
        }

        $nombrePlugin = $this->solicitud->consultaOEntrada('plugin', '');
        Plugins::eliminar($nombrePlugin);
        Cache::limpiar();
    }

    private function accionSubirPlugin(): void
    {
        if ($this->permisos->permitirActualizar === false) {
            Tools::registro()->advertencia('no-permitido-actualizar');
            return;
        } elseif ($this->validarTokenFormulario() === false) {
            return;
        }

        $exito = true;
        $archivosSubidos = $this->solicitud->archivos->obtenerArray('plugin');
        foreach ($archivosSubidos as $archivoSubido) {
            if ($archivoSubido->esValido() === false) {
                Tools::registro()->error($archivoSubido->obtenerMensajeError());
                continue;
            }

            if ($archivoSubido->obtenerTipoMime() !== 'application/zip') {
                Tools::registro()->error('archivo-no-soportado');
                continue;
            }

            if (Plugins::agregar($archivoSubido->obtenerRutaTemporal(), $archivoSubido->obtenerNombreOriginal()) === false) {
                $exito = false;
            }

            unlink($archivoSubido->obtenerRutaTemporal());
        }

        Cache::limpiar();

        if ($exito) {
            Tools::registro()->noticia('recargando');
            $this->redirigir($this->url(), 3);
        }
    }
}