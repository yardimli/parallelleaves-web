<?php

	declare(strict_types=1);

// Include Composer autoloader
	require_once __DIR__ . '/../vendor/autoload.php';

// MODIFIED: Initialize Dotenv to load variables from the .env file located in the root directory.
// We use safeLoad() so the application doesn't crash if the .env file is missing
// (e.g., in production environments where environment variables might be set at the server level).
	$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
	$dotenv->safeLoad();

// MODIFIED: Database Configuration loaded from .env with fallbacks
	define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
	define('DB_NAME', $_ENV['DB_NAME'] ?? 'parallel_leaves');
	define('DB_USER', $_ENV['DB_USER'] ?? 'root');
	define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// MODIFIED: API Keys loaded from .env
	define('OPEN_ROUTER_API_KEY', $_ENV['OPEN_ROUTER_API_KEY'] ?? '');
	define('FAL_API_KEY', $_ENV['FAL_API_KEY'] ?? '');

// MODIFIED: Application Settings loaded from .env with fallbacks
	define('APP_VERSION', $_ENV['APP_VERSION'] ?? '0.1.7');
	define('OPEN_ROUTER_MODEL', $_ENV['OPEN_ROUTER_MODEL'] ?? 'openai/gpt-oss-120b');

// Directories
// Added a fallback in case realpath() returns false (prevents path corruption)
	define('BASE_DIR', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
	define('USER_DATA_DIR', BASE_DIR . '/userData');
	define('TEMP_DIR', USER_DATA_DIR . '/temp');
	define('IMAGES_DIR', USER_DATA_DIR . '/images');
	define('DOWNLOADS_DIR', USER_DATA_DIR . '/downloads');
	define('DICTS_DIR', USER_DATA_DIR . '/dictionaries');

// Ensure directories exist
	foreach ([USER_DATA_DIR, TEMP_DIR, IMAGES_DIR, DOWNLOADS_DIR, DICTS_DIR] as $dir) {
		if (!is_dir($dir)) {
			// Changed 0777 to 0755.
			// 0777 triggers 500 Internal Server Errors on many Apache configurations (suPHP/FastCGI) due to strict security policies.
			mkdir($dir, 0755, true);
		}
	}
