<?php
namespace ERPIA\Core\Base\Contract;

use ERPIA\Core\Base\Translator;
use ERPIA\Core\Model\Base\PurchaseDocument;
use ERPIA\Core\Model\User;

interface PurchasesModInterface
{
    public function apply(PurchaseDocument &$model, array $formData, User $user);

    public function applyBefore(PurchaseDocument &$model, array $formData, User $user);

    public function assets(): void;

    public function newBtnFields(): array;

    public function newFields(): array;

    public function newModalFields(): array;

    public function renderField(Translator $i18n, PurchaseDocument $model, string $field): ?string;
}