<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2023-2025 ERPIA Team
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

namespace ERPIA\Lib\ExtendedController;

use Exception;
use ERPIA\Core\Logger;
use ERPIA\Core\FileManager;
use ERPIA\Dinamic\Model\AttachedFile;
use ERPIA\Dinamic\Model\AttachedFileRelation;
use ERPIA\Dinamic\Model\ProductImage;

/**
 * Auxiliary Method for product images management.
 *
 * @author ERPIA Team
 */
trait ProductImagesTrait
{
    /**
     * Add an HTML view to the controller.
     * 
     * @param string $viewName
     * @param string $fileName
     * @param string $modelName
     * @param string $viewTitle
     * @param string $viewIcon
     * @return HtmlView
     */
    abstract protected function addHtmlView(string $viewName, string $fileName, string $modelName, string $viewTitle, string $viewIcon = 'fa-brands fa-html5');

    /**
     * Validate the form token.
     * 
     * @return bool
     */
    abstract protected function validateFormToken(): bool;

    /**
     * Add a list of images.
     *
     * @return bool
     */
    protected function addImageAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Logger::warning('not-allowed-modify');
            return false;
        } elseif (false === $this->validateFormToken()) {
            return false;
        }

        $count = 0;
        $uploadedFiles = $this->request->files->getArray('uploaded_images');
        foreach ($uploadedFiles as $uploadedFile) {
            if (false === $uploadedFile->isValid()) {
                Logger::error($uploadedFile->getErrorMessage());
                continue;
            }

            if (false === strpos($uploadedFile->getMimeType(), 'image/')) {
                Logger::error('file-not-supported');
                continue;
            }

            try {
                $targetFolder = FileManager::getUploadsDirectory();
                FileManager::ensureDirectoryExists($targetFolder);
                $originalName = $uploadedFile->getClientOriginalName();
                $uploadedFile->move($targetFolder, $originalName);
                
                $fileId = $this->createAttachedFileRecord($originalName);
                if (empty($fileId)) {
                    Logger::error('record-save-error');
                    return true;
                }

                $productId = $this->createProductImageRecord($fileId);
                if (empty($productId)) {
                    Logger::error('record-save-error');
                    return true;
                }

                $this->createFileRelationRecord($productId, $fileId);
                ++$count;
            } catch (Exception $exception) {
                Logger::error($exception->getMessage());
                return true;
            }
        }

        Logger::notice('images-added-correctly', ['%count%' => $count]);
        return true;
    }

    /**
     * Add view for product images.
     *
     * @param string $viewName
     */
    protected function createViewsProductImages(string $viewName = 'EditProductImage'): void
    {
        $this->addHtmlView($viewName, 'Tab/ProductImage', 'ProductImage', 'images', 'fa-solid fa-images');
    }

    /**
     * Delete an image.
     *
     * @return bool
     */
    protected function deleteImageAction(): bool
    {
        if (false === $this->permissions->allowDelete) {
            Logger::warning('not-allowed-delete');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return false;
        }

        $imageId = $this->request->input('image_id');
        $productImage = new ProductImage();
        if (false === $productImage->load($imageId)) {
            return true;
        }

        $this->dataBase->beginTransaction();
        if ($productImage->remove() && $productImage->getAttachedFile()->remove()) {
            $this->dataBase->commit();
            Logger::notice('record-deleted-correctly');
            return true;
        }

        $this->dataBase->rollback();
        Logger::error('record-delete-error');
        return true;
    }

    /**
     * Create the record in the AttachedFile model
     * and returns its identifier.
     *
     * @param string $filePath
     * @return int
     */
    protected function createAttachedFileRecord(string $filePath): int
    {
        $newFile = new AttachedFile();
        $newFile->path = $filePath;
        $newFile->store();
        return $newFile->id;
    }

    /**
     * Create the record in the ProductImage model
     * and returns its product_id.
     *
     * @param int $fileId
     * @return ?int
     */
    protected function createProductImageRecord(int $fileId): ?int
    {
        $productImage = new ProductImage();
        $productImage->product_id = $this->request->input('product_id');
        $productImage->file_id = $fileId;

        $reference = $this->request->input('reference', '');
        $productImage->reference = empty($reference) ? null : $reference;
        return $productImage->store() ? $productImage->product_id : null;
    }

    /**
     * Create the record in the AttachedFileRelation model.
     *
     * @param int $productId
     * @param int $fileId
     */
    protected function createFileRelationRecord(int $productId, int $fileId): void
    {
        $fileRelation = new AttachedFileRelation();
        $fileRelation->file_id = $fileId;
        $fileRelation->model_name = 'Product';
        $fileRelation->model_id = $productId;
        $fileRelation->username = $this->user->username;
        $fileRelation->store();
    }
}