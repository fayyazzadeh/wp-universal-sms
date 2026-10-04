<?php
/**
 * Plugin Name: WP Universal SMS
 * Description: Provider-agnostic SMS gateway for WordPress.
 * Version: 0.1.0-beta
 * Author: Fayyazdeh
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-universal-sms
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';

if (is_readable($autoload)) {
    require_once $autoload;
}

if (class_exists(Fayyazdeh\UniversalSms\Plugin::class)) {
    Fayyazdeh\UniversalSms\Plugin::boot();
}
