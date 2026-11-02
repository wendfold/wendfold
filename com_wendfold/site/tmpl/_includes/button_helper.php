<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

if (!function_exists('hcButtonClasses')) {
    function hcButtonClasses(string $role = 'secondary', string $extra = ''): string
    {
        static $isYootheme = null;

        if ($isYootheme === null) {
            $templateName = '';

            try {
                $template = Factory::getApplication()->getTemplate(true);
                $templateName = (string) ($template->template ?? '');
            } catch (Throwable $e) {
                $templateName = '';
            }

            $isYootheme = stripos($templateName, 'yootheme') !== false;
        }

        $role = strtolower(trim($role));
        $roleMap = [
            'action' => 'hc-btn-action',
            'secondary' => 'hc-btn-secondary',
            'back' => 'hc-btn-back',
            'danger' => 'hc-btn-danger',
        ];

        if (!isset($roleMap[$role])) {
            $role = 'secondary';
        }

        $frameworkMap = $isYootheme
            ? [
                'action' => ['uk-button', 'uk-button-primary'],
                'secondary' => ['uk-button', 'uk-button-default'],
                'back' => ['uk-button', 'uk-button-secondary'],
                'danger' => ['uk-button', 'uk-button-danger'],
            ]
            : [
                'action' => ['btn', 'btn-primary'],
                'secondary' => ['btn', 'btn-outline-secondary'],
                'back' => ['btn', 'btn-secondary'],
                'danger' => ['btn', 'btn-danger'],
            ];

        $classes = ['hc-btn', $roleMap[$role]];

        foreach ($frameworkMap[$role] as $className) {
            $classes[] = $className;
        }

        $extraClasses = preg_split('/\s+/', trim($extra)) ?: [];

        foreach ($extraClasses as $className) {
            if ($className === '') {
                continue;
            }

            $classes[] = $className;

            if ($className === 'hc-btn-sm') {
                $classes[] = $isYootheme ? 'uk-button-small' : 'btn-sm';
            }
        }

        return implode(' ', array_values(array_unique($classes)));
    }
}
