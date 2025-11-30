<?php
namespace ERP\Core\Base\Contract;

use ERPIA\Core\Base\Translator;
use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Model\User;

interface SalesModInterface
{
    public function apply(SalesDocument &$model, array $formData, User $user);

    public function applyBefore(SalesDocument &$model, array $formData, User $user);

    public function assets(): void;

    public function newBtnFields(): array;

    public function newFields(): array;

    public function newModalFields(): array;

    public function renderField(Translator $i18n, SalesDocument $model, string $field): ?string;
}