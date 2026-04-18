<?php
	// MODIFIED: Added session check to protect the page
	session_start();
	if (!isset($_SESSION['user'])) {
		header('Location: login.php');
		exit;
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Translation Memory</title>
	<link rel="stylesheet" href="node_modules/bootstrap-icons/font/bootstrap-icons.css">
	<link rel="stylesheet" href="dist/styles.css">
</head>
<body class="bg-base-100 min-h-screen p-8 text-base-content">
<div class="container mx-auto max-w-7xl">
	<div class="flex justify-between items-center mb-6 pb-4 border-b border-base-300">
		<h1 class="text-4xl font-bold">Translation Memory</h1>
		<a href="index.php" class="btn btn-sm btn-outline">Back to Dashboard</a>
	</div>
	<div id="tm-container">
		<p>Loading...</p>
	</div>
</div>
<script src="js/api.js"></script>
<script src="src/js/theme.js"></script>
<script type="module" src="src/js/translation-memory.js"></script>
</body>
</html>
