<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2019-2024 ERPIA Team
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
 * Description of ButtonBlock
 *
 * @author ERPIA Team
 */
class ButtonBlock extends BaseBlock
{
    use ExtensionsTrait;

    /** @var string */
    protected $buttonText;
    
    /** @var string */
    protected $buttonUrl;

    /**
     * Constructor
     * @param string $label Button text
     * @param string $link Button URL
     * @param string $css CSS classes
     * @param string $style Inline styles
     */
    public function __construct(string $label, string $link, string $css = '', string $style = '')
    {
        $this->customCss = $css;
        $this->inlineStyles = $style;
        $this->buttonText = $label;
        $this->buttonUrl = $link;
    }

    /**
     * Render the button block
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

        $cssClass = empty($this->customCss) ? 'btn w-100' : $this->customCss;
        return '<span class="' . $cssClass . '">'
            . '<a href="' . $this->buildButtonUrl() . '">' . $this->buttonText . '</a></span>';
    }

    /**
     * Build the button URL with verification code
     * @return string
     */
    protected function buildButtonUrl(): string
    {
        if (empty($this->verificationCode)) {
            return $this->buttonUrl;
        }

        $separator = (parse_url($this->buttonUrl, PHP_URL_QUERY) === null) ? '?' : '&';
        return $this->buttonUrl . $separator . 'verificode=' . $this->verificationCode;
    }
}