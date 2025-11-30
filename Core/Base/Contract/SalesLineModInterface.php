<?php
namespace ERP\Core\Base\Contract;

use ERPIA\Core\Base\Translator;
use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Model\Base\SalesDocumentLine;

interface SalesLineModInterface
{
    public function apply(SalesDocument &$model, array &$lines, array $formData);

    public function applyToLine(array $formData, SalesDocumentLine &$line, string $id);

    public function assets(): void;

    public function getFastLine(SalesDocument $model, array $formData): ?SalesDocumentLine;

    public function map(array $lines, SalesDocument $model): array;

    public function newFields(): array;

    public function newModalFields(): array;

    public function newTitles(): array;

    public function renderField(Translator $i18n, string $idlinea, SalesDocumentLine $line, SalesDocument $model, string $field): ?string;

    public function renderTitle(Translator $i18n, SalesDocument $model, string $field): ?string;
}