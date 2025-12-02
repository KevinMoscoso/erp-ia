<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\ExtendedController\PanelController;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\Email\NewMail;
use ERPIA\Dinamic\Model\EmailNotification;

class ConfigEmail extends PanelController
{
    public function obtenerDatosPagina(): array
    {
        $datos = parent::obtenerDatosPagina();
        $datos['menu'] = 'administracion';
        $datos['titulo'] = 'correo';
        $datos['icono'] = 'fas fa-envelope';
        return $datos;
    }

    protected function crearVistas(): void
    {
        $this->establecerPlantilla('EditarConfiguracion');
        $this->crearVistaCorreo();
        $this->crearVistaCorreosEnviados();
        $this->crearVistaNotificacionesCorreo();
    }

    protected function crearVistaCorreo(string $nombreVista = 'ConfigCorreo'): void
    {
        $this->agregarVistaEdicion($nombreVista, 'Settings', 'correo', 'fas fa-envelope')
            ->definirConfiguracion('btnNuevo', false)
            ->definirConfiguracion('btnEliminar', false);
    }

    protected function crearVistaNotificacionesCorreo(string $nombreVista = 'ListaNotificacionCorreo'): void
    {
        $this->agregarVistaLista($nombreVista, 'EmailNotification', 'notificaciones', 'fas fa-bell')
            ->agregarCamposBusqueda(['cuerpo', 'nombre', 'asunto'])
            ->agregarOrdenPor(['fecha'], 'fecha')
            ->agregarOrdenPor(['nombre'], 'nombre', 1)
            ->agregarFiltroCheckbox('habilitado')
            ->definirConfiguracion('btnNuevo', false);

        $this->agregarBoton($nombreVista, [
            'accion' => 'habilitar-notificacion',
            'color' => 'success',
            'icono' => 'fas fa-check-square',
            'etiqueta' => 'habilitar'
        ]);

        $this->agregarBoton($nombreVista, [
            'accion' => 'deshabilitar-notificacion',
            'color' => 'warning',
            'icono' => 'far fa-square',
            'etiqueta' => 'deshabilitar'
        ]);
    }

    protected function crearVistaCorreosEnviados(string $nombreVista = 'ListaCorreoEnviado'): void
    {
        $this->agregarVistaLista($nombreVista, 'EmailSent', 'correos-enviados', 'fas fa-paper-plane')
            ->agregarCamposBusqueda(['destinatario', 'cuerpo', 'asunto'])
            ->agregarOrdenPor(['fecha'], 'fecha', 2)
            ->definirConfiguracion('btnNuevo', false);

        $usuarios = $this->modeloCodigo->todo('usuarios', 'nick', 'nick');
        $remitentes = $this->modeloCodigo->todo('emails_sent', 'email_from', 'email_from');

        $this->vistaLista($nombreVista)
            ->agregarFiltroSelect('nick', 'usuario', 'nick', $usuarios)
            ->agregarFiltroSelect('from', 'remitente', 'email_from', $remitentes)
            ->agregarFiltroPeriodo('fecha', 'periodo', 'fecha', true)
            ->agregarFiltroCheckbox('abierto')
            ->agregarFiltroCheckbox('adjunto', 'tiene-adjuntos');
    }

    protected function accionEditar(): bool
    {
        if (parent::accionEditar() === false) {
            return false;
        }

        $idLogo = Tools::configuracion('correo', 'idlogo');
        if (!empty($idLogo) && $this->esInstalacionOffline()) {
            Tools::registro()->advertencia('advertencia-logo-correo-offline');
        }

        return true;
    }

    protected function accionCambiarEstadoNotificacion(bool $estado): void
    {
        if ($this->validarTokenFormulario() === false) {
            return;
        } elseif ($this->usuario->puede('EditarEmailNotification', 'actualizar') === false) {
            Tools::registro()->advertencia('no-permitido-modificar');
            return;
        }

        $codigos = $this->solicitud->solicitud->obtenerArray('codigos');
        if (is_array($codigos) === false) {
            return;
        }

        foreach ($codigos as $codigo) {
            $notificacion = new EmailNotification();
            if ($notificacion->cargar($codigo) === false) {
                continue;
            }

            $notificacion->habilitado = $estado;
            if ($notificacion->guardar() === false) {
                Tools::registro()->advertencia('error-guardar-registro');
                return;
            }
        }

        Tools::registro()->noticia('registro-actualizado-correctamente');
    }

    protected function ejecutarDespuesAccion($accion)
    {
        if ($accion === 'probarcorreo') {
            $this->accionProbarCorreo();
        }

        parent::ejecutarDespuesAccion($accion);
    }

    protected function ejecutarPrevioAccion($accion)
    {
        switch ($accion) {
            case 'deshabilitar-notificacion':
                $this->accionCambiarEstadoNotificacion(false);
                break;
            case 'habilitar-notificacion':
                $this->accionCambiarEstadoNotificacion(true);
                break;
        }

        return parent::ejecutarPrevioAccion($accion);
    }

    private function esInstalacionOffline(): bool
    {
        $urlSitio = Tools::urlSitio();
        $host = (string)parse_url($urlSitio, PHP_URL_HOST);

        if (str_ends_with($host, 'localhost')
            || str_ends_with($host, 'local')
            || str_starts_with($host, '127.')
        ) {
            return true;
        }

        return false;
    }

    protected function cargarDatos($nombreVista, $vista)
    {
        $this->tieneDatos = true;

        switch ($nombreVista) {
            case 'ConfigCorreo':
                $vista->cargarDatos('correo');
                $vista->modelo->nombre = 'correo';
                $this->cargarValoresMailer($nombreVista);
                if ($vista->modelo->mailer === 'SMTP') {
                    $this->agregarBoton($nombreVista, [
                        'accion' => 'probarcorreo',
                        'color' => 'info',
                        'icono' => 'fas fa-envelope',
                        'etiqueta' => 'probar'
                    ]);
                }
                break;

            case 'ListaNotificacionCorreo':
            case 'ListaCorreoEnviado':
                $vista->cargarDatos();
                break;
        }
    }

    protected function cargarValoresMailer(string $nombreVista): void
    {
        $columna = $this->vistas[$nombreVista]->columnaParaNombre('mailer');
        if ($columna && $columna->widget->obtenerTipo() === 'select') {
            $columna->widget->establecerValoresDesdeArray(NewMail::obtenerMailers(), true, false);
        }
    }

    protected function accionProbarCorreo(): void
    {
        if ($this->accionEditar() === false) {
            return;
        }

        $correo = new NewMail();
        if ($correo->probar()) {
            Tools::registro()->noticia('prueba-correo-ok');
            return;
        }

        Tools::registro()->advertencia('prueba-correo-error');
    }
}