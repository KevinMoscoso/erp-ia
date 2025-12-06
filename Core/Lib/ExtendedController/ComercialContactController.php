<?php

namespace ERPIA\Lib\ExtendedController;

use ERPIA\Core\DatabaseWhere;
use ERPIA\Core\DataSource\WarehouseDataSource;
use ERPIA\Core\DataSource\CurrencyDataSource;
use ERPIA\Core\DataSource\ExerciseDataSource;
use ERPIA\Core\DataSource\CompanyDataSource;
use ERPIA\Core\DataSource\PaymentMethodDataSource;
use ERPIA\Core\DataSource\SeriesDataSource;
use ERPIA\Core\InvoiceOperation;
use ERPIA\Core\Logger;
use ERPIA\Core\Translation;
use ERPIA\Core\Config;
use ERPIA\Lib\BusinessDocumentGenerator;
use ERPIA\Models\Customer;
use ERPIA\Models\Supplier;
use ERPIA\Models\Contact;
use ERPIA\Models\EmailSent;
use ERPIA\Models\Subaccount;

/**
 * Controller for editing commercial contacts with document history
 */
abstract class CommercialContactController extends EditController
{
    use ListBusinessActionTrait;
    use DocumentFilesTrait;
    
    /** @var array */
    private $loggingLevels = ['critical', 'error', 'info', 'notice', 'warning'];
    
    /**
     * Add common filters to document list views
     */
    protected function addCommonFilters(string $viewName, string $documentType): void
    {
        $listView = $this->getListView($viewName);
        
        $statusCondition = [new DatabaseWhere('document_type', $documentType)];
        $statusOptions = $this->codeModel->getAll('document_status', 'status_id', 'name', true, $statusCondition);
        
        $listView->addDateRangeFilter('date_range', 'period', 'document_date')
            ->addNumericFilter('min_total', 'total_amount', 'total', '>=')
            ->addNumericFilter('max_total', 'total_amount', 'total', '<=')
            ->addSelectFilter('status_id', 'status', 'status_id', $statusOptions);
        
        if (!$this->permissions->restrictToOwner) {
            $userOptions = $this->codeModel->getAll('users', 'username', 'username');
            if (count($userOptions) > 1) {
                $listView->addSelectFilter('username', 'user', 'username', $userOptions);
            }
        }
        
        $companyOptions = CompanyDataSource::getCodeModel();
        if (count($companyOptions) > 2) {
            $listView->addSelectFilter('company_id', 'company', 'company_id', $companyOptions);
        }
        
        $warehouseOptions = WarehouseDataSource::getCodeModel();
        if (count($warehouseOptions) > 2) {
            $listView->addSelectFilter('warehouse_code', 'warehouse', 'warehouse_code', $warehouseOptions);
        }
        
        $seriesOptions = SeriesDataSource::getCodeModel();
        if (count($seriesOptions) > 2) {
            $listView->addSelectFilter('series_code', 'series', 'series_code', $seriesOptions);
        }
        
        $operationOptions = [['code' => '', 'description' => '------']];
        foreach (InvoiceOperation::getAll() as $code => $label) {
            $operationOptions[] = [
                'code' => $code,
                'description' => Translation::translate($label)
            ];
        }
        $listView->addSelectFilter('operation_type', 'operation', 'operation_type', $operationOptions);
        
        $paymentOptions = PaymentMethodDataSource::getCodeModel();
        if (count($paymentOptions) > 2) {
            $listView->addSelectFilter('payment_code', 'payment_method', 'payment_code', $paymentOptions);
        }
        
        $currencyOptions = CurrencyDataSource::getCodeModel();
        if (count($currencyOptions) > 2) {
            $listView->addSelectFilter('currency_code', 'currency', 'currency_code', $currencyOptions);
        }
        
        $listView->addCheckboxFilter('has_surcharge', 'surcharge', 'surcharge_total', '!=', 0)
            ->addCheckboxFilter('has_retention', 'retention', 'retention_total', '!=', 0)
            ->addCheckboxFilter('has_supplied', 'supplied_amount', 'supplied_total', '!=', 0)
            ->addCheckboxFilter('has_attachments', 'attachments', 'attachment_count', '!=', 0);
    }
    
    /**
     * Set custom widget values when loading main data
     */
    abstract protected function setCustomWidgets(string $viewName): void;
    
    /**
     * Validate subaccount code length
     */
    protected function validateSubaccountLength(?string $code): void
    {
        if (empty($code)) {
            return;
        }
        
        $exercises = ExerciseDataSource::getAll();
        foreach ($exercises as $exercise) {
            if ($exercise->isOpen() && strlen($code) !== $exercise->subaccountLength) {
                Logger::warning('Subaccount length mismatch', [
                    'code' => $code,
                    'expected_length' => $exercise->subaccountLength
                ]);
            }
        }
    }
    
    /**
     * Validate VAT information using VIES
     */
    protected function validateViesAction(): bool
    {
        $model = $this->getModel();
        $code = $this->request->get('code');
        
        if (!$model->loadById($code)) {
            return true;
        }
        
        if ($model->validateVies()) {
            Logger::notice('VIES validation successful', [
                'vat_number' => $model->taxId
            ]);
        }
        
        return true;
    }
    
    /**
     * Create contact list view
     */
    protected function createContactListView(string $viewName = 'EditContactAddress'): void
    {
        $this->addEditListView($viewName, 'Contact', 'addresses-and-contacts', 'fas fa-address-book');
    }
    
    /**
     * Create customer document list view
     */
    protected function createCustomerDocumentView(string $viewName, string $model, string $label): void
    {
        $this->createDocumentListView($viewName, $model, $label, $this->getCustomerDocumentFields());
    }
    
    /**
     * Create email list view
     */
    protected function createEmailListView(string $viewName = 'ListEmailSent'): void
    {
        $this->addListView($viewName, 'EmailSent', 'sent-emails', 'fas fa-envelope')
            ->addSortOrder(['sent_date'], 'date', 2)
            ->addSearchFields(['recipient', 'body', 'subject']);
        
        // Hide recipient column
        $this->views[$viewName]->setColumnState('recipient', true);
        
        // Disable new button
        $this->setViewSetting($viewName, 'newButton', false);
        
        // Add filters
        $this->getListView($viewName)->addDateRangeFilter('period', 'sent_date', 'sent_date', true);
    }
    
    /**
     * Create product line view
     */
    protected function createProductLineView(string $viewName, string $model, string $label = 'products'): void
    {
        $this->addListView($viewName, $model, $label, 'fas fa-cubes')
            ->addSortOrder(['line_id'], 'code', 2)
            ->addSortOrder(['quantity'], 'quantity')
            ->addSortOrder(['total_amount'], 'amount')
            ->addSearchFields(['reference', 'description']);
        
        // Button configuration
        $this->setViewSetting($viewName, 'deleteButton', false);
        $this->setViewSetting($viewName, 'newButton', false);
        $this->setViewSetting($viewName, 'printButton', true);
    }
    
    /**
     * Create document list view with common configuration
     */
    private function createDocumentListView(string $viewName, string $model, string $label, array $fieldConfig): void
    {
        // Create view
        $this->addListView($viewName, $model, $label, 'fas fa-copy')
            ->addSortOrder(['code'], 'code')
            ->addSortOrder(['document_date', 'time'], 'date', 2)
            ->addSortOrder(['number'], 'number')
            ->addSortOrder([$fieldConfig['number_field']], $fieldConfig['number_title'])
            ->addSortOrder(['total'], 'amount')
            ->addSearchFields(['code', 'notes', $fieldConfig['number_field']]);
        
        // Hide link column
        $this->getListView($viewName)->hideColumn($fieldConfig['link_field'], true);
        
        // Add filters
        $this->addCommonFilters($viewName, $model);
    }
    
    /**
     * Create receipt list view
     */
    protected function createReceiptListView(string $viewName, string $model): void
    {
        $this->addListView($viewName, $model, 'receipts', 'fas fa-dollar-sign')
            ->addSortOrder(['date'], 'date')
            ->addSortOrder(['payment_date'], 'payment_date')
            ->addSortOrder(['due_date'], 'expiration', 2)
            ->addSortOrder(['amount'], 'amount')
            ->addSearchFields(['document_code', 'notes']);
        
        // Filters
        $this->getListView($viewName)->addDateRangeFilter('date_range', 'date', 'date');
        $this->getListView($viewName)->addDateRangeFilter('due_range', 'due_date', 'due_date');
        
        // Buttons
        $this->addReceiptPaymentButton($viewName);
        $this->setViewSetting($viewName, 'printButton', true);
        $this->setViewSetting($viewName, 'newButton', false);
        $this->setViewSetting($viewName, 'deleteButton', false);
        
        // Hide columns
        $this->views[$viewName]->setColumnState('customer', true);
        $this->views[$viewName]->setColumnState('supplier', true);
    }
    
    /**
     * Create subaccount list view
     */
    protected function createSubaccountListView(string $viewName = 'ListSubaccount'): void
    {
        $this->addListView($viewName, 'Subaccount', 'subaccounts', 'fas fa-book')
            ->addSortOrder(['account_code'], 'code')
            ->addSortOrder(['exercise_code'], 'exercise', 2)
            ->addSortOrder(['description'], 'description')
            ->addSortOrder(['balance'], 'balance')
            ->addSearchFields(['account_code', 'description']);
        
        // Button configuration
        $this->setViewSetting($viewName, 'deleteButton', false);
        $this->setViewSetting($viewName, 'newButton', false);
        $this->setViewSetting($viewName, 'checkboxes', false);
    }
    
    /**
     * Create supplier document list view
     */
    protected function createSupplierDocumentView(string $viewName, string $model, string $label): void
    {
        $this->createDocumentListView($viewName, $model, $label, $this->getSupplierDocumentFields());
    }
    
    /**
     * Execute actions before loading data
     */
    protected function executePreviousAction(string $action): bool
    {
        $allowUpdate = $this->permissions->allowUpdate;
        $selectedCodes = $this->request->getArray('codes');
        $model = $this->views[$this->activeView]->model;
        
        switch ($action) {
            case 'add-file':
                return $this->handleAddFile();
                
            case 'approve-document':
                return $this->handleApproveDocument($selectedCodes, $model, $allowUpdate, $this->database);
                
            case 'approve-document-same-date':
                BusinessDocumentGenerator::setSameDate(true);
                return $this->handleApproveDocument($selectedCodes, $model, $allowUpdate, $this->database);
                
            case 'check-vies':
                return $this->validateViesAction();
                
            case 'delete-file':
                return $this->handleDeleteFile();
                
            case 'edit-file':
                return $this->handleEditFile();
                
            case 'generate-accounting-entries':
                return $this->handleGenerateAccountingEntries($model, $allowUpdate, $this->database);
                
            case 'group-document':
                return $this->handleGroupDocument($selectedCodes, $model);
                
            case 'lock-invoice':
                return $this->handleLockInvoice($selectedCodes, $model, $allowUpdate, $this->database);
                
            case 'pay-receipt':
                return $this->handlePayReceipt($selectedCodes, $model, $allowUpdate, $this->database, $this->user->username);
                
            case 'unlink-file':
                return $this->handleUnlinkFile();
        }
        
        return parent::executePreviousAction($action);
    }
    
    /**
     * Get customer document field configuration
     */
    private function getCustomerDocumentFields(): array
    {
        return [
            'link_field' => 'customer',
            'number_field' => 'secondary_number',
            'number_title' => 'secondary_number'
        ];
    }
    
    /**
     * Get supplier document field configuration
     */
    private function getSupplierDocumentFields(): array
    {
        return [
            'link_field' => 'supplier',
            'number_field' => 'supplier_number',
            'number_title' => 'supplier_number'
        ];
    }
    
    /**
     * Load data for specific views
     */
    protected function loadViewData(string $viewName, BaseView $view): void
    {
        $mainViewName = $this->getMainViewName();
        
        switch ($viewName) {
            case $mainViewName:
                parent::loadViewData($viewName, $view);
                $this->setCustomWidgets($viewName);
                
                if ($view->model->exists() && !empty($view->model->taxId)) {
                    $this->addButton($viewName, [
                        'action' => 'check-vies',
                        'color' => 'info',
                        'icon' => 'fas fa-check-double',
                        'label' => 'verify-vies'
                    ]);
                }
                break;
                
            case 'docfiles':
                $this->loadDocumentFilesData($view, $this->getModelClassName(), $this->getModel()->getPrimaryKeyValue());
                break;
                
            case 'ListSubaccount':
                $accountCode = $this->getViewFieldValue($mainViewName, 'account_code');
                $conditions = [new DatabaseWhere('account_code', $accountCode)];
                $view->loadData('', $conditions);
                $this->setViewSetting($viewName, 'active', $view->recordCount > 0);
                break;
                
            case 'ListEmailSent':
                $emailAddress = $this->getViewFieldValue($mainViewName, 'email');
                
                if (empty($emailAddress)) {
                    $this->setViewSetting($viewName, 'active', false);
                    break;
                }
                
                $conditions = [new DatabaseWhere('recipient', $emailAddress)];
                $view->loadData('', $conditions);
                
                // Add button to send new email
                $this->addButton($viewName, [
                    'action' => 'SendMail?email=' . urlencode($emailAddress),
                    'color' => 'success',
                    'icon' => 'fas fa-envelope',
                    'label' => 'send',
                    'type' => 'link'
                ]);
                break;
        }
    }
    
    /**
     * Synchronize contact information with subject
     */
    protected function syncContactInfo($subject): void
    {
        $contact = $subject->getDefaultContact();
        
        if ($contact === null) {
            return;
        }
        
        $contact->email = $subject->email;
        $contact->fax = $subject->fax;
        $contact->phone1 = $subject->phone1;
        $contact->phone2 = $subject->phone2;
        
        // Synchronize tax data for validation
        $contact->taxId = $subject->taxId;
        $contact->taxIdType = $subject->taxIdType;
        
        $contact->save();
    }
}