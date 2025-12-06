<?php

namespace ERPIA\Lib\Export;

use ERPIA\Core\Logger;
use ERPIA\Lib\Email\NewMail;
use ERPIA\Models\Base\BusinessDocument;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\Response;

/**
 * Export to PDF and prepare for email sending
 */
class MAILExport extends PDFExport
{
    /** @var array */
    protected $mailParameters = [];
    
    /**
     * Adds a business document page and captures model info
     */
    public function addBusinessDocumentPage($model): bool
    {
        $this->captureModelInfo($model);
        return parent::addBusinessDocumentPage($model);
    }
    
    /**
     * Adds a model page and captures model info
     */
    public function addModelPage($model, $columns, $title = ''): bool
    {
        $this->captureModelInfo($model);
        return parent::addModelPage($model, $columns, $title);
    }
    
    /**
     * Saves PDF to temporary folder and redirects to email sending page
     */
    public function show(Response &$response)
    {
        $fileName = $this->generateFileName();
        $filePath = $this->getTempAttachmentPath($fileName);
        
        // Create directory if it doesn't exist
        $directory = dirname($filePath);
        if (!$this->createDirectory($directory)) {
            Logger::error('Unable to create temporary directory for email attachments');
            return;
        }
        
        // Save PDF to temporary file
        if (!$this->savePdfToFile($filePath)) {
            Logger::error('Failed to save PDF file for email attachment');
            return;
        }
        
        $this->mailParameters['attachment_file'] = $fileName;
        
        // Redirect to email sending page
        $this->redirectToEmailPage($response);
    }
    
    /**
     * Captures model class name and primary key value(s)
     */
    protected function captureModelInfo(ModelClass $model): void
    {
        $this->mailParameters['model_class'] = $model->modelClassName();
        
        $primaryValue = $model->primaryColumnValue();
        
        if (!isset($this->mailParameters['primary_key'])) {
            $this->mailParameters['primary_key'] = $primaryValue;
        } elseif (!isset($this->mailParameters['primary_keys'])) {
            $this->mailParameters['primary_keys'] = $primaryValue;
        } else {
            $this->mailParameters['primary_keys'] .= ',' . $primaryValue;
        }
    }
    
    /**
     * Generates a unique file name for the PDF
     */
    private function generateFileName(): string
    {
        $baseName = $this->getOutputFileName();
        $timestamp = time();
        return $baseName . '_email_' . $timestamp . '.pdf';
    }
    
    /**
     * Gets the full temporary file path for the attachment
     */
    private function getTempAttachmentPath(string $fileName): string
    {
        return ERPIA_ROOT . '/' . NewMail::ATTACHMENTS_TEMP_PATH . $fileName;
    }
    
    /**
     * Creates a directory if it doesn't exist
     */
    private function createDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        
        return is_writable($path);
    }
    
    /**
     * Saves the PDF document to a file
     */
    private function savePdfToFile(string $filePath): bool
    {
        $pdfContent = $this->getDocument();
        return file_put_contents($filePath, $pdfContent) !== false;
    }
    
    /**
     * Redirects to the email sending page with parameters
     */
    private function redirectToEmailPage(Response &$response): void
    {
        $queryString = http_build_query($this->mailParameters);
        $redirectUrl = 'SendMail?' . $queryString;
        
        $response->setHeader('Refresh', '0; ' . $redirectUrl);
    }
}