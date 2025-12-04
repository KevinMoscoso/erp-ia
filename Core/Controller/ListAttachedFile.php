<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2018-2023 ERPIA Contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\ExtendedController\ListController;
use ERPIA\Core\Model\AttachedFile;
use ERPIA\Core\App\Logger;
use ERPIA\Core\App\Formatter;
use ERPIA\Core\App\Configuration;
use ZipArchive;

/**
 * Controller to list the items in the AttachedFile model
 *
 * @author ERPIA Contributors
 */
class ListAttachedFile extends ListController
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'library';
        $pageData['icon'] = 'fa-solid fa-book-open';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $this->createViewsFiles();
        $this->showStorageLimitWarning();
    }

    /**
     * Creates and configures the attached files view
     *
     * @param string $viewName
     */
    protected function createViewsFiles(string $viewName = 'ListAttachedFile'): void
    {
        // Add the main view for attached files
        $this->addView($viewName, 'AttachedFile', 'attached-files', 'fa-solid fa-paperclip');
        
        // Configure search fields
        $this->addSearchFields(['filename', 'mimetype']);
        
        // Configure default order
        $this->addOrderBy(['idfile'], 'code');
        $this->addOrderBy(['date', 'hour'], 'date', 2);
        $this->addOrderBy(['filename'], 'file-name');
        $this->addOrderBy(['size'], 'size');

        // Date period filter
        $this->addFilterPeriod($viewName, 'date', 'period', 'date');

        // MIME type filter
        $types = $this->codeModel->getAll('attached_files', 'mimetype', 'mimetype');
        $this->addFilterSelect($viewName, 'mimetype', 'type', 'mimetype', $types);

        // Download button
        $this->addButton($viewName, [
            'action' => 'download',
            'icon' => 'fa-solid fa-download',
            'label' => 'download'
        ]);
    }

    /**
     * Execute actions before reading data
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        if ($action === 'download') {
            return $this->downloadAction();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Download selected files as a ZIP archive
     *
     * @return bool
     */
    protected function downloadAction(): bool
    {
        $codes = $this->request->request->getArray('codes');
        if (empty($codes)) {
            Logger::warning('no-selected-item');
            return true;
        }

        // Create ZIP file
        $zip = new ZipArchive();
        $filename = 'attached-files.zip';
        $storagePath = Configuration::get('file_storage_path', 'MyFiles');
        $filepath = $storagePath . '/' . $filename;
        
        if ($zip->open($filepath, ZipArchive::CREATE) !== true) {
            Logger::warning('error-creating-zip-file');
            return true;
        }

        // Add selected files to the ZIP
        $model = $this->views[$this->active]->model;
        foreach ($codes as $code) {
            $file = $model->get($code);
            if ($file && file_exists($file->getFullPath())) {
                $zip->addFile($file->getFullPath(), $file->idfile . '_' . $file->filename);
            }
        }

        // Close the ZIP archive
        $zip->close();

        // Send the ZIP file to the client
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);

        // Delete the temporary ZIP file
        unlink($filepath);

        // Prevent rendering the template
        $this->setTemplate(false);
        return false;
    }

    /**
     * Show a warning if storage usage is close to the limit
     */
    protected function showStorageLimitWarning(): void
    {
        $limit = AttachedFile::getStorageLimit();
        if (empty($limit)) {
            return;
        }

        // Check if used storage is above 80% of the limit
        $used = AttachedFile::getStorageUsed();
        if ($used > 0.8 * $limit) {
            $free = $limit - $used;
            Logger::warning('storage-limit-almost', [
                '%free%' => Formatter::bytes($free),
                '%limit%' => Formatter::bytes($limit),
                '%used%' => Formatter::bytes($used)
            ]);
        }
    }
}