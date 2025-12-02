<?php

namespace ERPIA\Core\Contract;

use ERPIA\Core\Model\Base\SalesDocument;

interface SalesModInterface
{
    public function aplicarCambios(SalesDocument &$documento, array $datosFormulario): void;

    public function prepararCambios(SalesDocument &$documento, array $datosFormulario): void;

    public function incluirRecursos(): void;

    public function definirCamposBoton(): array;

    public function definirCamposAdicionales(): array;

    public function definirCamposModales(): array;

    public function presentarCampo(SalesDocument $documento, string $campo): ?string;
}