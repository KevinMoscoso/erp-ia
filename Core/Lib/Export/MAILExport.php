<?php

namespace ERPIA\Lib\Export;

use ERPIA\Lib\Email\EmailManager;
use ERPIA\Models\Base\BusinessDocument;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\FileSystemHelper;
use ERPIA\Core\Logger;
use ERPIA\Core\HttpResponse;

/**
 * PDF export for email sending
 */
class EmailExport extends PDFExportBase
{
    /** @var array */
    protected $emailParameters = [];
    
    /**
     * Export a business document and capture model data for email
     */
    public function exportBusinessDocument(BusinessDocument $model): bool
    {
        $this->captureModelData($model);
        return parent::exportBusinessDocument($model);
    }
    
    /**
     * Export a single model and capture model data for email
     */
    public function exportSingleModel(ModelClass $model, array $columns, string $title = ''): bool
    {
        $this->captureModelData($model);
        return parent::exportSingleModel($model, $columns, $title);
    }
    
    /**
     * Save PDF to temporary folder and redirect to email sending page
     */
    public function sendToResponse(HttpResponse &$response): void
    {
        $fileName = $this->generateUniqueFilename();
        $filePath = $this->getTemporaryFilePath($fileName);
        
        if (!$this->savePdfToFile($filePath)) {
            Logger::error('Failed to save PDF for email attachment');
            return;
        }
        
        $this->prepareEmailRedirect($response, $fileName);
    }
    
    /**
     * Capture model information for email parameters
     */
    private function captureModelData(ModelClass $model): void
    {
        $this->emailParameters['model_class'] = $model->getModelClassName();
        
        $primaryKeyValue = $model->getPrimaryKeyValue();
        
        if (!isset($this->emailParameters['primary_key'])) {
            $this->emailParameters['primary_key'] = $primaryKeyValue;
        } elseif (!isset($this->emailParameters['primary_keys'])) {
            $this->emailParameters['primary_keys'] = $primaryKeyValue;
        } else {
            $this->emailParameters['primary_keys'] .= ',' . $primaryKeyValue;
        }
    }
    
    /**
     * Generate unique filename for PDF attachment
     */
    private function generateUniqueFilename(): string
    {
        $baseName = $this->getOutputFilename();
        $timestamp = time();
        return $baseName . '_email_' . $timestamp . '.pdf';
    }
    
    /**
     * Get full temporary file path
     */
    private function getTemporaryFilePath(string $fileName): string
    {
        $tempDir = ERPIA_ROOT . '/' . EmailManager::ATTACHMENTS_TEMP_PATH;
        return $tempDir . $fileName;
    }
    
    /**
     * Save PDF document to file
     */
    private function savePdfToFile(string $filePath): bool
    {
        $tempDir = dirname($filePath);
        
        if (!FileSystemHelper::createDirectory($tempDir)) {
            Logger::error('Cannot create temporary directory for email attachments', [
                'directory' => $tempDir
            ]);
            return false;
        }
        
        $pdfContent = $this->getDocumentContent();
        $bytesWritten = file_put_contents($filePath, $pdfContent);
        
        return $bytesWritten !== false;
    }
    
    /**
     * Prepare HTTP response for email sending redirect
     */
    private function prepareEmailRedirect(HttpResponse $response, string $fileName): void
    {
        $this->emailParameters['file_name'] = $fileName;
        
        $queryString = http_build_query($this->emailParameters);
        $redirectUrl = '/email/send?' . $queryString;
        
        $response->setRedirect($redirectUrl);
    }
}