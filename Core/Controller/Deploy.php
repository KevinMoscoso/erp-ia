<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Contract\ControllerInterface;
use ERPIA\Core\CrashReport;
use ERPIA\Core\Plugins;
use ERPIA\Core\Tools;

class Deploy implements ControllerInterface
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
        switch ($_GET['accion'] ?? '') {
            case 'desactivar-plugins':
                $this->accionDesactivarPlugins();
                break;

            case 'reconstruir':
                $this->accionReconstruir();
                break;

            default:
                $this->accionDesplegar();
                break;
        }

        echo '<a href="' . Tools::config('ruta') . '/">Recargar</a>';
    }

    protected function accionDesplegar(): void
    {
        if (is_dir(Tools::carpeta('Dinamic'))) {
            echo '<p>Despliegue no necesario. La carpeta Dinamic ya existe.</p>';
            return;
        }

        Plugins::desplegar();

        echo '<p>Despliegue completado.</p>';
    }

    protected function accionDesactivarPlugins(): void
    {
        if (Tools::config('deshabilitar_acciones_despliegue', false)) {
            echo '<p>Acciones de despliegue ya deshabilitadas.</p>';
            return;
        }

        if (false === CrashReport::validarToken($_GET['token'] ?? '')) {
            echo '<p>Token inválido.</p>';
            return;
        }

        foreach (Plugins::habilitados() as $nombre) {
            Plugins::desactivar($nombre, false);
        }

        echo '<p>Plugins desactivados.</p>';
    }

    protected function accionReconstruir(): void
    {
        if (Tools::config('deshabilitar_acciones_despliegue', false)) {
            echo '<p>Acciones de despliegue ya deshabilitadas.</p>';
            return;
        }

        if (false === CrashReport::validarToken($_GET['token'] ?? '')) {
            echo '<p>Token inválido.</p>';
            return;
        }

        Plugins::desplegar();

        echo '<p>Reconstrucción completada.</p>';
    }
}