<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Response;
use ERPIA\Core\Template\ApiController;
use ERPIA\Core\UploadedFile;
use ERPIA\Dinamic\Model\AttachedFile;
use ERPIA\Core\Tools;

class ApiUploadFiles extends ApiController
{
    protected function ejecutarRecurso(): void
    {
        if (!in_array($this->solicitud->metodo(), ['POST', 'PUT'])) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_METODO_NO_PERMITIDO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'Metodo no permitido',
                ]);
            return;
        }

        $archivosSubidos = [];
        $archivos = $this->solicitud->archivos->obtenerArray('files');
        foreach ($archivos as $archivo) {
            if ($archivoSubido = $this->subirArchivo($archivo)) {
                $archivosSubidos[] = $archivoSubido;
            }
        }

        $this->respuesta->json([
            'files' => $archivosSubidos,
        ]);
    }

    private function subirArchivo(UploadedFile $archivoSubido): ?AttachedFile
    {
        if ($archivoSubido->esValido() === false) {
            return null;
        }

        if ($archivoSubido->extension() === 'php') {
            return null;
        }

        $destino = Tools::carpeta('MisArchivos') . '/';
        $nombreDestino = $archivoSubido->obtenerNombreOriginal();
        if (file_exists($destino . $nombreDestino)) {
            $nombreDestino = mt_rand(1, 999999) . '_' . $nombreDestino;
        }

        if ($archivoSubido->mover($destino, $nombreDestino)) {
            $archivo = new AttachedFile();
            $archivo->ruta = $nombreDestino;
            if ($archivo->guardar()) {
                return $archivo;
            }
        }

        return null;
    }
}