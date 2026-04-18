<?php

	declare(strict_types=1);

	require_once __DIR__ . '/config.php';

	function getDB(): mysqli
	{
		static $mysqli = null;

		if ($mysqli === null) {
			// Tell mysqli to throw exceptions for errors (equivalent to PDO::ERRMODE_EXCEPTION)
			mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

			try {
				$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

				// Set charset to utf8mb4
				$mysqli->set_charset("utf8mb4");
			} catch (mysqli_sql_exception $e) {
				die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
			}
		}

		return $mysqli;
	}
