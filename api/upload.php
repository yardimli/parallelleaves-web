<?php

	declare(strict_types=1);

	require_once __DIR__ . '/config.php';

	header('Content-Type: application/json');

	if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
		echo json_encode(['success' => false, 'message' => 'No file uploaded']);
		exit;
	}

	$file = $_FILES['file'];
	$filename = time() . '-' . basename($file['name']);
	$targetPath = TEMP_DIR . '/' . $filename;

	if (move_uploaded_file($file['tmp_name'], $targetPath)) {
		echo json_encode([
			'success' => true,
			'filePath' => $targetPath,
			'url' => '/userData/temp/' . $filename
		]);
	} else {
		echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
	}
