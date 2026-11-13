<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Shared widget chrome: border / background from layout config
 * (show_border, bg_color, bg_opacity).
 */

if (!function_exists('widget_cfg')) {
    function widget_cfg($cfg)
    {
        if (is_object($cfg)) {
            return $cfg;
        }
        return (object) (is_array($cfg) ? $cfg : []);
    }
}

if (!function_exists('widget_border_class')) {
    function widget_border_class($cfg, $default = 'border rounded')
    {
        $cfg = widget_cfg($cfg);
        $show = $cfg->show_border ?? '1';
        if ($show === '0' || $show === 0 || $show === false || $show === 'false') {
            return '';
        }
        return $default;
    }
}

if (!function_exists('widget_chrome_class')) {
    /** Outer grid-stack card chrome (border/shadow) — follows show_border */
    function widget_chrome_class($cfg)
    {
        $cfg = widget_cfg($cfg);
        $show = $cfg->show_border ?? '1';
        if ($show === '0' || $show === 0 || $show === false || $show === 'false') {
            return '';
        }
        return 'widget-chrome';
    }
}

if (!function_exists('widget_bg_class')) {
    function widget_bg_class($cfg, $default = '')
    {
        $cfg = widget_cfg($cfg);
        $color = trim((string) ($cfg->bg_color ?? ''));
        if ($color !== '') {
            return '';
        }
        return $default;
    }
}

if (!function_exists('widget_style_attr')) {
    function widget_style_attr($cfg, $base = '')
    {
        $cfg = widget_cfg($cfg);
        $parts = [];
        $base = trim((string) $base);
        if ($base !== '') {
            $parts[] = rtrim($base, ';');
        }

        $color = trim((string) ($cfg->bg_color ?? ''));
        if ($color !== '') {
            $opacity = isset($cfg->bg_opacity) && $cfg->bg_opacity !== ''
                ? (float) $cfg->bg_opacity
                : 100;
            $opacity = max(0, min(100, $opacity)) / 100;
            $rgba = widget_hex_to_rgba($color, $opacity);
            if ($rgba !== null) {
                $parts[] = 'background-color: ' . $rgba;
            } else {
                $parts[] = 'background-color: ' . $color;
            }
        }

        return implode('; ', $parts);
    }
}

if (!function_exists('widget_hex_to_rgba')) {
    function widget_hex_to_rgba($hex, $alpha = 1)
    {
        $hex = ltrim(trim((string) $hex), '#');
        if (strlen($hex) === 3 && ctype_xdigit($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return null;
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $alpha = max(0, min(1, (float) $alpha));
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}
