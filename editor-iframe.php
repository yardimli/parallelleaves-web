<?php
	// MODIFIED: Added session check to protect the page
	session_start();
	if (!isset($_SESSION['user'])) {
		echo '<script>window.parent.location.href = "login.php";</script>';
		exit;
	}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
	<meta charset="UTF-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="node_modules/bootstrap-icons/font/bootstrap-icons.css">
	<link rel="stylesheet" href="dist/styles.css">
</head>
<body class="bg-transparent text-base-content">
<div id="editor-container" class="prose prose-sm dark:prose-invert max-w-none p-4 rounded-lg bg-accent/5"></div>
<script src="js/api.js"></script>
<script src="dist/editor-iframe-bundle.js"></script>
</body>
</html>
