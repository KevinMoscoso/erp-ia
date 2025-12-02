<?php

namespace ERPIA\Core\Contract;

use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Model\Base\SalesDocumentLine;

interface SalesLineModInterface
{
    public function modificarDocumento(SalesDocument &$documento, array &$lineas, array $datosFormulario): void;

    public function modificarLineaIndividual(array $datosFormulario, SalesDocumentLine &$linea, string $identificador): void;

    public function cargarActivos(): void;

    public function obtenerLineaPreconfigurada(SalesDocument $documento, array $datosFormulario): ?SalesDocumentLine;

    public function reorganizarLineas(array $lineas, SalesDocument $documento): array;

    public function obtenerCamposExtras(): array;

    public function obtenerCamposVentanaModal(): array;

    public function obtenerEtiquetasExtras(): array;

    public function generarCampoVista(string $idLinea, SalesDocumentLine $linea, SalesDocument $documento, string $campo): ?string;

    public function generarTituloVista(SalesDocument $documento, string $campo): ?string;
}