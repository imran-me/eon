<?php
/* ============================================================
   EON server — bootstrap. Loaded by every API endpoint and cron.
   Plain PHP 8.2, no framework: works on Hostinger shared hosting
   and inside the ERP's public/ folder alike.
   ============================================================ */
declare(strict_types=1);

define('EON_ROOT', __DIR__);
define('EON_VERSION', '0.1.0');

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Dhaka');

// composer autoload (anthropic-ai/sdk) if installed — optional
if (is_file(EON_ROOT . '/vendor/autoload.php')) require_once EON_ROOT . '/vendor/autoload.php';

require_once EON_ROOT . '/lib/Config.php';
require_once EON_ROOT . '/lib/Log.php';
require_once EON_ROOT . '/lib/Http.php';
require_once EON_ROOT . '/lib/Db.php';
require_once EON_ROOT . '/lib/Dataset.php';
require_once EON_ROOT . '/lib/Erp.php';
require_once EON_ROOT . '/lib/Analytics.php';
require_once EON_ROOT . '/lib/Memory.php';
require_once EON_ROOT . '/lib/Tools.php';
require_once EON_ROOT . '/lib/Brain.php';
require_once EON_ROOT . '/lib/Notify.php';

// mbstring is standard on Hostinger; keep working if a host lacks it
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $s): string { return strtolower($s); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $s): int { return strlen($s); } }
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $a, ?int $l = null): string { return $l === null ? substr($s, $a) : substr($s, $a, $l); } }

Config::load();
if (($tz = Config::get('company.timezone'))) date_default_timezone_set($tz);
