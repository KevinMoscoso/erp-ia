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
 * Description of BoxBlock
 *
 * @author ERPIA Team
 */
class BoxBlock extends BaseBlock
{
    use ExtensionsTrait;

    /** @var array */
    protected $childBlocks;

    /**
     * Constructor
     * @param array $blocks Array of child blocks
     * @param string $css CSS classes
     * @param string $style Inline styles
     */
    public function __construct(array $blocks, string $css = '', string $style = '')
    {
        $this->customCss = $css;
        $this->inlineStyles = $style;
        $this->childBlocks = $blocks;
    }

    /**
     * Render the box block
     * @param bool $footer Whether this block is a footer
     * @return string
     */
    public function render(bool $footer = false): string
    {
        $this->isFooter = $footer;
        
        // Allow extensions to override rendering
        $extensionOutput = $this->pipe('render');
        if (!empty($extensionOutput)) {
            return $extensionOutput;
        }

        $htmlContent = '';
        foreach ($this->childBlocks as $block) {
            if ($block instanceof BaseBlock) {
                $htmlContent .= $block->render($footer);
            }
        }

        $cssClass = empty($this->customCss) ? 'block mb-15' : $this->customCss;
        return '<div class="' . $cssClass . '">' . $htmlContent . '</div>';
    }
}