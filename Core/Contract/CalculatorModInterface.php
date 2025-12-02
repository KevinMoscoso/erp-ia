<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2018-2025 ERPIA Contributors
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

namespace ERPIA\Core\Contract;

use ERPIA\Core\Model\Base\BusinessDocument;
use ERPIA\Core\Model\Base\BusinessDocumentLine;

/**
 * Interface for calculator modifiers and extensions
 *
 * @author ERPIA Contributors
 */
interface CalculatorModInterface
{
    /**
     * Apply configurations and settings
     */
    public function apply(BusinessDocument &$document, array &$lines): bool;

    /**
     * Perform document calculation
     */
    public function calculate(BusinessDocument &$document, array &$lines): bool;

    /**
     * Calculate individual line
     */
    public function calculateLine(BusinessDocument $document, BusinessDocumentLine &$line): bool;

    /**
     * Clear and reset values
     */
    public function clear(BusinessDocument &$document, array &$lines): bool;

    /**
     * Modify calculated subtotals
     */
    public function getSubtotals(array &$subtotals, BusinessDocument $document, array $lines): bool;
}