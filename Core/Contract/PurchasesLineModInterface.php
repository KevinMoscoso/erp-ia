<?php

namespace ERPIA\Core\Contract;

use ERPIA\Core\Model\Base\BusinessDocumentLine;
use ERPIA\Core\Model\Base\PurchaseDocument;

interface PurchasesLineModInterface
{
    public function modifyDocument(PurchaseDocument &$doc, array &$lines, array $form): void;

    public function modifySingleLine(array $form, BusinessDocumentLine &$line, string $lineId): void;

    public function loadResources(): void;

    public function createQuickLine(PurchaseDocument $doc, array $form): ?BusinessDocumentLine;

    public function transformLines(array $lines, PurchaseDocument $doc): array;

    public function getAdditionalFields(): array;

    public function getModalFields(): array;

    public function getAdditionalTitles(): array;

    public function displayField(string $lineId, BusinessDocumentLine $line, PurchaseDocument $doc, string $field): ?string;

    public function displayTitle(PurchaseDocument $doc, string $field): ?string;
}