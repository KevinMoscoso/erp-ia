<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2023-2024 ERPIA Team
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

namespace ERPIA\Core\Lib\Email;

use ERPIA\Core\Template\ExtensionsTrait;

/**
 * Description of HtmlBlock
 *
 * @author ERPIA Team
 */
class HtmlBlock extends BaseBlock
{
    use ExtensionsTrait;

    /** @var string */
    protected $content;

    /**
     * Constructor
     * @param string $html HTML content
     */
    public function __construct(string $html)
    {
        $this->content = $html;
    }

    /**
     * Render the HTML block
     * @param bool $footer Whether this block is a footer
     * @return string
     */
    public function render(bool $footer = false): string
    {
        $this->isFooter = $footer;
        
        // Allow extensions to override rendering
        $extensionOutput = $this->pipe('render');
        if ($extensionOutput !== null) {
            return $extensionOutput;
        }

        return $this->content;
    }
}