<?php

namespace ERPIA\Lib\ExtendedController;

use ERPIA\Core\DatabaseWhere;
use ERPIA\Models\AttachedFileRelation;
use ERPIA\Models\Base\BusinessDocument;
use ERPIA\Core\Logger;
use ERPIA\Core\Translation;
use ERPIA\Core\FileSystemHelper;
use ERPIA\Models\AttachedFile;

/**
 * Trait for managing document attachments
 */
trait DocFilesTrait
{
    /**
     * Handle file upload and attachment
     */
    protected function handleAddFile(): bool
    {
        if (!$this->permissions->allowUpdate) {
            Logger::warning('Modification not permitted');
            return true;
        } elseif (!$this->validateAttachmentToken()) {
            return true;
        }

        $uploadedFiles = $this->request->getFiles('new_files');
        
        foreach ($uploadedFiles as $file) {
            if ($file === null) {
                continue;
            } elseif (!$file->isValid()) {
                Logger::error('File upload error: ' . $file->getError());
                continue;
            }

            // Block PHP files
            $blockedTypes = ['application/x-php', 'text/x-php', 'application/x-httpd-php'];
            if (in_array($file->getClientMimeType(), $blockedTypes)) {
                Logger::error('PHP files are not allowed');
                continue;
            }

            // Define destination path
            $destinationDir = ERPIA_ROOT . '/Storage/Uploads/';
            $originalName = $file->getClientOriginalName();
            $destinationPath = $destinationDir . $originalName;

            // Avoid filename collisions
            $counter = 1;
            while (file_exists($destinationPath)) {
                $info = pathinfo($originalName);
                $destinationPath = $destinationDir . 
                    $info['filename'] . '_' . $counter . '.' . $info['extension'];
                $counter++;
            }

            // Move uploaded file
            if (!$file->moveTo($destinationPath)) {
                Logger::error('Failed to move uploaded file');
                continue;
            }

            // Create file record
            $fileRecord = new AttachedFile();
            $fileRecord->setPath($originalName);
            $fileRecord->setStoragePath($destinationPath);
            
            if (!$fileRecord->save()) {
                Logger::error('Failed to save file record');
                return true;
            }

            // Create file relation
            $relation = new AttachedFileRelation();
            $relation->setFileId($fileRecord->getId());
            $relation->setModelClass($this->getModelClassName());
            $relation->setModelCode($this->request->get('code'));
            $relation->setModelId((int)$this->request->get('code'));
            $relation->setUserId($this->user->getId());
            $relation->setNotes($this->request->get('notes'));

            if (!$relation->save()) {
                Logger::error('Failed to save file relation');
                return true;
            }
        }

        // Update document attachment count if applicable
        if ($this->getModel() instanceof BusinessDocument) {
            $this->updateAttachmentCount();
        }

        Logger::notice('Files attached successfully');
        return true;
    }

    /**
     * Create document files view
     */
    protected function createDocumentFilesView(string $viewName = 'docfiles', string $template = 'Tabs/DocumentFiles'): void
    {
        $this->addHtmlView($viewName, $template, 'AttachedFileRelation', 'attachments', 'fas fa-paperclip');
    }

    /**
     * Handle file deletion
     */
    protected function handleDeleteFile(): bool
    {
        if (!$this->permissions->allowDelete) {
            Logger::warning('Delete operation not permitted');
            return true;
        } elseif (!$this->validateAttachmentToken()) {
            return true;
        }

        $relationId = $this->request->get('id');
        $relation = new AttachedFileRelation();
        
        if (!$relation->loadById($relationId)) {
            Logger::warning('Attachment relation not found');
            return true;
        }

        $modelCode = $this->request->get('code');
        if ($relation->getModelCode() !== $modelCode || 
            $relation->getModelClass() !== $this->getModelClassName()) {
            Logger::warning('Unauthorized deletion attempt');
            return true;
        }

        $file = $relation->getFile();
        $relation->remove();
        
        if ($file !== null) {
            $file->remove();
        }

        Logger::notice('File deleted successfully');

        // Update document attachment count if applicable
        if ($this->getModel() instanceof BusinessDocument) {
            $this->updateAttachmentCount();
        }

        return true;
    }

    /**
     * Handle file metadata update
     */
    protected function handleEditFile(): bool
    {
        if (!$this->permissions->allowUpdate) {
            Logger::warning('Modification not permitted');
            return true;
        } elseif (!$this->validateAttachmentToken()) {
            return true;
        }

        $relationId = $this->request->get('id');
        $relation = new AttachedFileRelation();
        
        if (!$relation->loadById($relationId)) {
            Logger::warning('Attachment relation not found');
            return true;
        }

        $modelCode = $this->request->get('code');
        if ($relation->getModelCode() !== $modelCode || 
            $relation->getModelClass() !== $this->getModelClassName()) {
            Logger::warning('Unauthorized modification attempt');
            return true;
        }

        $relation->setNotes($this->request->get('notes'));
        
        if (!$relation->save()) {
            Logger::error('Failed to update file metadata');
            return true;
        }

        Logger::notice('File metadata updated successfully');
        return true;
    }

    /**
     * Load attachment data for view
     */
    protected function loadDocumentFilesData($view, string $modelClass, $modelId): void
    {
        $conditions = [
            new DatabaseWhere('model_class', $modelClass)
        ];
        
        if (is_numeric($modelId)) {
            $conditions[] = new DatabaseWhere('model_id|model_code', $modelId, 'OR');
        } else {
            $conditions[] = new DatabaseWhere('model_code', $modelId);
        }
        
        $view->loadData('', $conditions, ['created_at' => 'DESC']);
    }

    /**
     * Handle file relation removal (unlink)
     */
    protected function handleUnlinkFile(): bool
    {
        if (!$this->permissions->allowUpdate) {
            Logger::warning('Modification not permitted');
            return true;
        } elseif (!$this->validateAttachmentToken()) {
            return true;
        }

        $relationId = $this->request->get('id');
        $relation = new AttachedFileRelation();
        
        if ($relation->loadById($relationId)) {
            $relation->remove();
        }

        Logger::notice('File unlinked successfully');

        // Update document attachment count if applicable
        if ($this->getModel() instanceof BusinessDocument) {
            $this->updateAttachmentCount();
        }

        return true;
    }

    /**
     * Update document attachment count
     */
    protected function updateAttachmentCount(): void
    {
        $model = $this->getModel();
        
        if (!$model instanceof BusinessDocument) {
            return;
        }

        $modelCode = $this->request->get('code');
        $count = AttachedFileRelation::count([
            ['model_class', '=', $this->getModelClassName()],
            ['model_id|model_code', '=', $modelCode, 'OR']
        ]);

        $model->setAttachmentCount($count);
        
        if (!$model->save()) {
            Logger::error('Failed to update attachment count');
        }
    }

    /**
     * Validate security token for attachment actions
     */
    private function validateAttachmentToken(): bool
    {
        $token = $this->request->get('multi_request_token', '');
        
        if (empty($token) || !$this->multiRequestProtection->isValid($token)) {
            Logger::warning('Invalid security token');
            return false;
        }

        if ($this->multiRequestProtection->exists($token)) {
            Logger::warning('Duplicate request detected');
            return false;
        }

        return true;
    }
}