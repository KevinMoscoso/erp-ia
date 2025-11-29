<?php
namespace ERPIA\Core\Base\Contract;

use ERPIA\Core\Base\Translator;
use ERPIA\Core\Model\Base\PurchaseDocument;
use ERPIA\Core\Model\Base\PurchaseDocumentLine;

interface PurchasesLineModInterface
{
    public function apply(PurchaseDocument &$model, array &$lines, array $formData);

    public function applyToLine(array $formData, PurchaseDocumentLine &$line, string $id);

    public function assets(): void;

    public function getFastLine(PurchaseDocument $model, array $formData): ?PurchaseDocumentLine;

    public function map(array $lines, PurchaseDocument $model): array;

    public function newFields(): array;

    public function newModalFields(): array;

    public function newTitles(): array;

    public function renderField(Translator $i18n, string $idlinea, PurchaseDocumentLine $line, PurchaseDocument $model, string $field): ?string;

    public function renderTitle(Translator $i18n, PurchaseDocument $model, string $field): ?string;
}