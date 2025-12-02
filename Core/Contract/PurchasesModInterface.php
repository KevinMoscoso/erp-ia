<?php

namespace ERPIA\Core\Contract;

use ERPIA\Core\Model\Base\PurchaseDocument;

interface PurchasesModInterface
{
    public function aplicarModificaciones(PurchaseDocument &$documento, array $datosFormulario): void;

    public function prepararModificaciones(PurchaseDocument &$documento, array $datosFormulario): void;

    public function cargarRecursos(): void;

    public function obtenerCamposBoton(): array;

    public function obtenerCamposAdicionales(): array;

    public function obtenerCamposModales(): array;

    public function mostrarCampo(PurchaseDocument $documento, string $campo): ?string;
}