<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\Controller;
use ERPIA\Core\Kernel;
use ERPIA\Core\Plugins;
use ERPIA\Core\UploadedFile;
use ERPIA\Dinamic\Model\AttachedFile;
use ERPIA\Dinamic\Model\Cliente;
use ERPIA\Dinamic\Model\FacturaCliente;
use ERPIA\Dinamic\Model\Producto;
use ERPIA\Dinamic\Model\User;

class About extends Controller
{
    public $informacion = [];

    public function obtenerDatosPagina(): array
    {
        $datos = parent::obtenerDatosPagina();
        $datos['menu'] = 'administracion';
        $datos['titulo'] = 'acerca_de';
        $datos['icono'] = 'fas fa-info-circle';
        return $datos;
    }

    public function nucleoPrivado(&$respuesta, $usuario, $permisos)
    {
        parent::nucleoPrivado($respuesta, $usuario, $permisos);
        $this->informacion = $this->recopilarInformacion();
    }

    private function recopilarInformacion(): array
    {
        $version_core = Kernel::version();
        $version_php = phpversion();
        $extensiones = get_loaded_extensions();
        $software_servidor = $_SERVER['SERVER_SOFTWARE'] ?? 'No disponible';
        $info_sistema = php_uname();
        $tipo_bd = $this->baseDatos->tipo();
        $version_bd = $this->baseDatos->version();
        $version_openssl = defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'No disponible';

        $limite_almacenamiento = AttachedFile::obtenerLimiteAlmacenamiento();
        $almacenamiento_usado = AttachedFile::obtenerAlmacenamientoUsado();
        $tamano_maximo_subida = UploadedFile::obtenerTamanoMaximoArchivo();

        $plugins = Plugins::listar();

        $limites = $this->calcularLimites();

        return [
            'version_core' => $version_core,
            'version_php' => $version_php,
            'extensiones' => $extensiones,
            'software_servidor' => $software_servidor,
            'info_sistema' => $info_sistema,
            'tipo_bd' => $tipo_bd,
            'version_bd' => $version_bd,
            'version_openssl' => $version_openssl,
            'limite_almacenamiento' => $limite_almacenamiento,
            'almacenamiento_usado' => $almacenamiento_usado,
            'tamano_maximo_subida' => $tamano_maximo_subida,
            'plugins' => $plugins,
            'limites' => $limites
        ];
    }

    private function calcularLimites(): array
    {
        $total_usuarios = User::contar();
        $total_productos = Producto::contar();
        $total_clientes = Cliente::contar();
        $total_facturas = FacturaCliente::contar();

        return [
            'usuarios' => $total_usuarios,
            'productos' => $total_productos,
            'clientes' => $total_clientes,
            'facturas' => $total_facturas
        ];
    }
}