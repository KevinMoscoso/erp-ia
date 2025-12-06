<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2022-2025 ERPIA Team
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

use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\Email\NewMail as DynamicNewMail;
use ERPIA\Dinamic\Model\EmailNotification;
use PHPMailer\PHPMailer\Exception;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Description of MailNotifier
 *
 * @author ERPIA Team
 */
class MailNotifier
{
    /**
     * Replace parameters in text using braces {param}
     *
     * @param string $text
     * @param array $params
     * @return string
     */
    public static function getText(string $text, array $params): string
    {
        foreach ($params as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $text = str_replace('{' . $key . '}', $value, $text);
            }
        }

        return $text;
    }

    /**
     * Send an email notification
     *
     * @param string $notificationName
     * @param string $email
     * @param string $name
     * @param array $params
     * @param array $attachments
     * @param array $mainBlocks
     * @param array $footerBlocks
     * @return bool
     * @throws Exception
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public static function send(
        string $notificationName,
        string $email,
        string $name = '',
        array $params = [],
        array $attachments = [],
        array $mainBlocks = [],
        array $footerBlocks = []
    ): bool {
        // Check if notification exists
        $notification = new EmailNotification();
        if (false === $notification->load($notificationName)) {
            Tools::log()->warning('email-notification-not-exists', ['%name%' => $notificationName]);
            return false;
        }

        // Check if notification is enabled
        if (false === $notification->enabled) {
            Tools::log()->warning('email-notification-disabled', ['%name%' => $notificationName]);
            return false;
        }

        // Create new mail instance
        $newMail = new DynamicNewMail();

        // Add default parameters
        if (!isset($params['email'])) {
            $params['email'] = $email;
        }
        if (!isset($params['name'])) {
            $params['name'] = $name;
        }
        if (!isset($params['verificode'])) {
            $params['verificode'] = $newMail->getVerificationCode();
        }

        $newMail->setRecipient($email, $name);
        $newMail->setSubject(static::getText($notification->subject, $params));
        $newMail->setBody(static::getText($notification->body, $params));
        
        static::processTextBlocks($newMail, $params);

        foreach ($mainBlocks as $block) {
            $newMail->addMainBlock($block);
        }

        foreach ($footerBlocks as $block) {
            $newMail->addFooterBlock($block);
        }

        foreach ($attachments as $attachment) {
            $newMail->addAttachment($attachment, basename($attachment));
        }

        return $newMail->send();
    }

    /**
     * Process text blocks marked with {block1}, {block2}, etc.
     * Blocks can be strings or objects extending BaseBlock.
     * If a block marker is not found, the block is added at the end of the email.
     *
     * @param DynamicNewMail $newMail
     * @param array $params
     */
    protected static function processTextBlocks(DynamicNewMail &$newMail, array $params): void
    {
        $bodyText = $newMail->getBody();
        if (empty($params) || empty($bodyText)) {
            return;
        }

        // Find all block markers {block1}, {block2}, etc.
        preg_match_all('/{block(\d+)}/', $bodyText, $matches);

        if (empty($matches[1])) {
            return;
        }

        $newMail->setBody('');
        $lastPosition = 0;

        foreach ($matches[1] as $blockIndex) {
            $blockMarker = '{block' . $blockIndex . '}';
            $markerPosition = strpos($bodyText, $blockMarker, $lastPosition);
            
            if ($markerPosition === false) {
                continue;
            }

            $textBetween = substr($bodyText, $lastPosition, $markerPosition - $lastPosition);
            $lastPosition = $markerPosition + strlen($blockMarker);

            if (!empty($textBetween)) {
                $newMail->addMainBlock(new TextBlock($textBetween));
            }

            $blockKey = 'block' . $blockIndex;
            if (isset($params[$blockKey]) && $params[$blockKey] instanceof BaseBlock) {
                $newMail->addMainBlock($params[$blockKey]);
            }
        }

        $remainingText = substr($bodyText, $lastPosition);
        if (!empty($remainingText)) {
            $newMail->addMainBlock(new TextBlock($remainingText));
        }
    }
}