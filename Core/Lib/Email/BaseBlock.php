<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2019-2023 ERPIA Team
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

/**
 * Description of BaseBlock
 *
 * @author ERPIA Team
 */
abstract class BaseBlock
{
    /** @var string */
    protected $customCss;
    
    /** @var bool */
    protected $isFooter = false;
    
    /** @var string */
    protected $inlineStyles;
    
    /** @var string */
    protected $verificationCode;

    /**
     * Render the block content
     * @param bool $footer Whether this block is a footer
     * @return string
     */
    abstract public function render(bool $footer = false): string;

    /**
     * Set verification code
     * @param string $code
     */
    public function setVerificationCode(string $code): void
    {
        $this->verificationCode = $code;
    }
}