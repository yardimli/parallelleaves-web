<?php

	require_once __DIR__ . '/config.php';

// MODIFIED: Added session check to upload endpoint
	session_start();
	if (!isset($_SESSION['user'])) {
		http_response_code(401);
		echo json_encode(['success' => false, 'message' => 'Unauthorized']);
		exit;
	}

// MODIFIED: Added CORS headers to prevent preflight failures on cross-origin requests
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: POST, OPTIONS');
	header('Access-Control-Allow-Headers: Content-Type, Authorization');
	header('Content-Type: application/json');

// MODIFIED: Handle OPTIONS request for CORS preflight
	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
		http_response_code(204);
		exit;
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
		echo json_encode(['success' => false, 'message' => 'No file uploaded']);
		exit;
	}

	$file = $_FILES['file'];

// MODIFIED: Added detailed error checking for the uploaded file (e.g., exceeds php.ini limits)
	if ($file['error'] !== UPLOAD_ERR_OK) {
		$uploadErrors = [
			UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
			UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
			UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
			UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
			UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
			UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
			UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
		];
		$errorMessage = $uploadErrors[$file['error']] ?? 'Unknown upload error.';
		echo json_encode(['success' => false, 'message' => $errorMessage]);
		exit;
	}

// MODIFIED: Sanitize the filename to prevent path traversal or invalid characters crashing the filesystem
	$safeFilename = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($file['name']));
	$filename = time() . '-' . $safeFilename;
	$targetPath = TEMP_DIR . '/' . $filename;

	if (move_uploaded_file($file['tmp_name'], $targetPath)) {
		echo json_encode([
			'success' => true,
			'filePath' => $targetPath,
			'url' => '/userData/temp/' . $filename
		]);
	} else {
		// MODIFIED: Check if directory is writable to provide a better error message instead of failing silently
		if (!is_writable(TEMP_DIR)) {
			echo json_encode(['success' => false, 'message' => 'Upload directory is not writable. Check permissions for: ' . TEMP_DIR]);
		} else {
			echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
		}
	}
