<?php
	require_once __DIR__ . '/config.php';
	require_once __DIR__ . '/db.php';
	require_once __DIR__ . '/utils.php';

	session_start();

	header('Content-Type: application/json');

// Safely decode input to prevent TypeError if file_get_contents returns false
	$rawInput = file_get_contents('php://input');
	$input = $rawInput ? json_decode($rawInput, true) : [];
	$channel = $input['channel'] ?? '';
	$args = $input['args'] ?? [];

	$db = getDB();
	$userId = $_SESSION['user']['id'] ?? 1;
// MODIFIED: Fetch the user's API key from the session
	$userApiKey = $_SESSION['user']['openrouter_api_key'] ?? '';

	try {
		$result = null;

		switch ($channel) {
			// --- System & Auth ---
			case 'splash:get-init-data':
				$result = ['version' => APP_VERSION, 'user' => $_SESSION['user'] ?? null, 'websiteUrl' => '#'];
				break;
			case 'auth:login':
				$creds = $args[0];
				// MODIFIED: Select openrouter_api_key as well
				$stmt = $db->prepare('SELECT id, username, password_hash, openrouter_api_key FROM users WHERE username = ?');
				$stmt->execute([$creds['username']]);
				$user = $stmt->get_result()->fetch_assoc();
				if ($user && password_verify($creds['password'], $user['password_hash'])) {
					unset($user['password_hash']);
					$_SESSION['user'] = $user;
					$result = ['success' => true, 'session' => ['user' => $user, 'token' => session_id()]];
				} else {
					$result = ['success' => false, 'message' => 'Invalid credentials'];
				}
				break;
			case 'auth:register':
				$data = $args[0];
				$stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
				$stmt->execute([$data['username']]);
				if ($stmt->get_result()->fetch_assoc()) {
					$result = ['success' => false, 'message' => 'Username already exists.'];
				} else {
					$hash = password_hash($data['password'], PASSWORD_DEFAULT);
					$db->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')->execute([$data['username'], $hash]);
					$result = ['success' => true];
				}
				break;
			case 'auth:logout':
				session_destroy();
				$result = ['success' => true];
				break;
			case 'auth:get-session':
				$result = isset($_SESSION['user']) ? ['user' => $_SESSION['user'], 'token' => session_id()] : null;
				break;
			case 'user:set-api-key':
				// MODIFIED: Added endpoint to update the user's API key
				$newKey = $args[0] ?? '';
				$stmt = $db->prepare('UPDATE users SET openrouter_api_key = ? WHERE id = ?');
				$stmt->execute([$newKey, $userId]);
				$_SESSION['user']['openrouter_api_key'] = $newKey;
				$result = ['success' => true];
				break;
			case 'templates:get':
				$path = BASE_DIR . '/public/templates/' . $args[0] . '.html';
				$result = file_exists($path) ? file_get_contents($path) : '';
				break;
			case 'i18n:get-lang-file':
				$lang = $args[0];
				$dir = BASE_DIR . '/public/lang/' . $lang;
				$merged = [];
				if (is_dir($dir)) {
					foreach (glob($dir . '/*.json') as $file) {
						$key = basename($file, '.json');
						$fileContent = file_get_contents($file);
						$merged[$key] = $fileContent ? json_decode($fileContent, true) : [];
					}
				}
				$result = json_encode($merged);
				break;

			case 'app:reset':
				$result = ['success' => true];
				break;

			// --- Session (Electron fallbacks) ---
			case 'session:getAvailableSpellCheckerLanguages':
				$result = ['en-US'];
				break;
			case 'session:getCurrentSpellCheckerLanguage':
				$result = 'en-US';
				break;
			case 'session:setSpellCheckerLanguage':
				$result = ['success' => true];
				break;

			// --- API Logs ---
			case 'logs:get':
				$page = max(1, (int)($args[0] ?? 1));
				$limit = 25;
				$offset = ($page - 1) * $limit;
				$total = $db->prepare('SELECT COUNT(*) FROM api_logs WHERE user_id = ?');
				$total->execute([$userId]);
				$countRow = $total->get_result()->fetch_row();
				$count = $countRow[0] ?? 0;

				$stmt = $db->prepare('SELECT id, action, request_payload, response_body, response_code, created_at FROM api_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
				$stmt->execute([$userId, $limit, $offset]);
				$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
				$result = ['logs' => $logs, 'totalPages' => ceil($count / $limit), 'currentPage' => $page];
				break;

			// --- Languages ---
			case 'languages:get-supported':
				$result = [
					'af' => 'Afrikaans',
					'bg' => 'Bulgarian',
					'ca' => 'Catalan',
					'zh-CN' => 'Chinese (Simplified)',
					'zh-TW' => 'Chinese (Traditional)',
					'cs' => 'Czech',
					'cy' => 'Welsh',
					'da' => 'Danish',
					'de' => 'German',
					'el' => 'Greek',
					'en-GB' => 'English (UK)',
					'en-US' => 'English (US)',
					'es-419' => 'Spanish (Latin America)',
					'es-AR' => 'Spanish (Argentina)',
					'es-ES' => 'Spanish (Spain)',
					'es-MX' => 'Spanish (Mexico)',
					'es-US' => 'Spanish (US)',
					'et' => 'Estonian',
					'fa' => 'Persian',
					'fo' => 'Faroese',
					'fr' => 'French',
					'he' => 'Hebrew',
					'hi' => 'Hindi',
					'hr' => 'Croatian',
					'hu' => 'Hungarian',
					'hy' => 'Armenian',
					'id' => 'Indonesian',
					'it' => 'Italian',
					'ja' => 'Japanese',
					'ko' => 'Korean',
					'lt' => 'Lithuanian',
					'lv' => 'Latvian',
					'nb' => 'Norwegian (Bokmål)',
					'nn' => 'Norwegian (Nynorsk)',
					'nl' => 'Dutch',
					'pl' => 'Polish',
					'pt-BR' => 'Portuguese (Brazil)',
					'pt-PT' => 'Portuguese (Portugal)',
					'ro' => 'Romanian',
					'ru' => 'Russian',
					'sh' => 'Serbo-Croatian',
					'sk' => 'Slovak',
					'sl' => 'Slovenian',
					'sq' => 'Albanian',
					'sr' => 'Serbian',
					'sv' => 'Swedish',
					'ta' => 'Tamil',
					'tg' => 'Tajik',
					'tr' => 'Turkish',
					'uk' => 'Ukrainian',
					'vi' => 'Vietnamese',
				];
				break;

			// --- Books ---
			case 'books:getAllWithCovers':
				$stmt = $db->prepare("
        SELECT n.*, i.image_local_path as cover_path, 
        (SELECT COUNT(id) FROM chapters WHERE book_id = n.id) as chapter_count
        FROM user_books n
        LEFT JOIN images i ON n.id = i.book_id AND i.image_type LIKE '%cover%'
        WHERE n.user_id = ? ORDER BY n.updated_at DESC
    ");
				$stmt->execute([$userId]);
				$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
				foreach ($books as &$book) {
					$chStmt = $db->prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ?');
					$chStmt->execute([$book['id']]);
					$chapters = $chStmt->get_result()->fetch_all(MYSQLI_ASSOC);
					$book['source_word_count'] = array_sum(array_map(fn($c) => countWordsInHtml($c['source_content'] ?? ''), $chapters));
					$book['target_word_count'] = array_sum(array_map(fn($c) => countWordsInHtml($c['target_content'] ?? ''), $chapters));
					if ($book['cover_path']) {
						$book['cover_path'] = '/userData/images/' . $book['cover_path'];
					}
				}
				$result = $books;
				break;
			case 'books:getAllWithTranslationMemory':
				$stmt = $db->prepare('SELECT DISTINCT b.id, b.title FROM user_books_translation_memory tm JOIN user_books b ON tm.book_id = b.id WHERE b.user_id = ? ORDER BY b.title ASC');
				$stmt->execute([$userId]);
				$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
				break;
			case 'books:getOne':
			case 'books:getFullManuscript':
				$bookId = $args[0];
				$stmt = $db->prepare('SELECT * FROM user_books WHERE id = ? AND user_id = ?');
				$stmt->execute([$bookId, $userId]);
				$book = $stmt->get_result()->fetch_assoc();
				if ($book) {
					$chStmt = $db->prepare('SELECT * FROM chapters WHERE book_id = ? ORDER BY chapter_order');
					$chStmt->execute([$bookId]);
					$book['chapters'] = $chStmt->get_result()->fetch_all(MYSQLI_ASSOC);
					foreach ($book['chapters'] as &$chapter) {
						$chapter['source_word_count'] = countWordsInHtml($chapter['source_content'] ?? '');
						$chapter['target_word_count'] = countWordsInHtml($chapter['target_content'] ?? '');
					}
				}
				$result = $book;
				break;
			case 'books:getAllBookContent':
				$bookId = $args[0];
				$stmt = $db->prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ?');
				$stmt->execute([$bookId]);
				$chapters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
				$combined = '';
				foreach ($chapters as $c) {
					$combined .= ($c['source_content'] ?? '') . ($c['target_content'] ?? '');
				}
				$result = ['success' => true, 'combinedHtml' => $combined];
				break;
			case 'books:getForExport':
				$bookId = $args[0];
				$stmt = $db->prepare('SELECT id, title, author, target_language FROM user_books WHERE id = ? AND user_id = ?');
				$stmt->execute([$bookId, $userId]);
				$book = $stmt->get_result()->fetch_assoc();
				if (!$book) {
					throw new Exception('Book not found.');
				}
				$chStmt = $db->prepare('SELECT id, title, target_content FROM chapters WHERE book_id = ? ORDER BY chapter_order');
				$chStmt->execute([$bookId]);
				$book['chapters'] = $chStmt->get_result()->fetch_all(MYSQLI_ASSOC);
				$result = ['success' => true, 'data' => $book];
				break;
			case 'books:createBlank':
				$data = $args[0];
				$stmt = $db->prepare('INSERT into user_books (user_id, title, source_language, target_language) VALUES (?, ?, ?, ?)');
				$stmt->execute([$userId, $data['title'], $data['source_language'], $data['target_language']]);
				$bookId = $db->insert_id;
				$chStmt = $db->prepare('INSERT INTO chapters (book_id, title, chapter_order, source_content, target_content) VALUES (?, ?, ?, ?, ?)');
				for ($i = 1; $i <= 10; $i++) {
					$chStmt->execute([$bookId, "Chapter $i", $i, '<p></p>', '<p></p>']);
				}
				$result = ['success' => true, 'bookId' => $bookId];
				break;
			case 'books:updateField':
			case 'books:updateMeta':
				$data = $args[0];
				$stmt = $db->prepare('UPDATE user_books SET title = ?, author = ? WHERE id = ? AND user_id = ?');
				$stmt->execute([$data['title'], $data['author'], $data['bookId'], $userId]);
				$result = ['success' => true];
				break;
			case 'books:updateProseSettings':
				$data = $args[0];
				$stmt = $db->prepare('UPDATE user_books SET source_language = ?, target_language = ? WHERE id = ? AND user_id = ?');
				$stmt->execute([$data['source_language'], $data['target_language'], $data['bookId'], $userId]);
				$result = ['success' => true];
				break;
			case 'books:updatePromptSettings':
				$data = $args[0];
				$allowedTypes = ['rephrase', 'translate'];
				if (!in_array($data['promptType'], $allowedTypes)) {
					throw new Exception('Invalid prompt type.');
				}
				$field = $data['promptType'] . '_settings';
				$stmt = $db->prepare("UPDATE user_books SET $field = ? WHERE id = ? AND user_id = ?");
				$stmt->execute([json_encode($data['settings']), $data['bookId'], $userId]);
				$result = ['success' => true];
				break;
			case 'books:updateBookCover':
				$data = $args[0];
				$bookId = $data['bookId'];
				$coverInfo = $data['coverInfo'];
				$localPath = null;
				$imageType = 'unknown';

				if ($coverInfo['type'] === 'remote') {
					$paths = storeImageFromUrl($coverInfo['data'], $bookId, 'cover');
					$localPath = $paths['original_path'] ?? null;
					$imageType = 'generated';
				} elseif ($coverInfo['type'] === 'local') {
					$paths = storeImageFromPath($coverInfo['data'], $bookId, 'cover-upload');
					$localPath = $paths['original_path'] ?? null;
					$imageType = 'upload';
				}

				if (!$localPath) {
					throw new Exception('Failed to store the new cover image.');
				}

				$oldImage = $db->prepare('SELECT image_local_path FROM images WHERE book_id = ?');
				$oldImage->execute([$bookId]);
				$old = $oldImage->get_result()->fetch_assoc();
				if ($old && $old['image_local_path']) {
					@unlink(IMAGES_DIR . '/' . $old['image_local_path']);
				}

				$db->prepare('DELETE FROM images WHERE book_id = ?')->execute([$bookId]);
				$db->prepare('INSERT INTO images (user_id, book_id, image_local_path, thumbnail_local_path, image_type) VALUES (?, ?, ?, ?, ?)')
					->execute([$userId, $bookId, $localPath, $localPath, $imageType]);

				$result = ['success' => true, 'imagePath' => '/userData/images/' . $localPath];
				break;
			case 'books:delete':
				$bookId = $args[0];
				$images = $db->prepare('SELECT image_local_path FROM images WHERE book_id = ?');
				$images->execute([$bookId]);
				foreach ($images->get_result()->fetch_all(MYSQLI_ASSOC) as $img) {
					@unlink(IMAGES_DIR . '/' . $img['image_local_path']);
				}
				$db->prepare('DELETE FROM images WHERE book_id = ?')->execute([$bookId]);
				$db->prepare('DELETE FROM user_books WHERE id = ? AND user_id = ?')->execute([$bookId, $userId]);
				$result = ['success' => true];
				break;
			case 'books:exportToDocx':
				$data = $args[0];
				$filename = preg_replace('/[^a-z0-9]/i', '_', $data['title']) . '_' . time() . '.doc';
				$filePath = DOWNLOADS_DIR . '/' . $filename;
				$html = "<html><head><meta charset='utf-8'></head><body>" . $data['htmlContent'] . "</body></html>";
				file_put_contents($filePath, $html);
				$result = ['success' => true, 'downloadUrl' => '/userData/downloads/' . $filename, 'filename' => $filename];
				break;
			case 'books:findHighestMarkerNumber':
				$result = findHighestMarkerNumber($args[0], $args[1]);
				break;

			// --- Chapters ---
			case 'chapters:updateField':
				$data = $args[0];
				$allowedFields = ['title', 'target_content', 'source_content'];
				if (!in_array($data['field'], $allowedFields)) {
					throw new Exception('Invalid field specified.');
				}
				$stmt = $db->prepare("UPDATE chapters SET {$data['field']} = ? WHERE id = ?");
				$stmt->execute([$data['value'], $data['chapterId']]);
				$result = ['success' => true];
				break;
			case 'chapters:getRawContent':
				$data = $args[0];
				$allowedFields = ['source_content', 'target_content'];
				if (!in_array($data['field'], $allowedFields)) {
					throw new Exception('Invalid field specified.');
				}
				$stmt = $db->prepare("SELECT {$data['field']} FROM chapters WHERE id = ?");
				$stmt->execute([$data['chapterId']]);
				$row = $stmt->get_result()->fetch_row();
				$result = $row[0] ?? null;
				break;
			case 'chapters:rename':
				$data = $args[0];
				$db->prepare('UPDATE chapters SET title = ? WHERE id = ?')->execute([$data['newTitle'], $data['chapterId']]);
				$result = ['success' => true];
				break;
			case 'chapters:delete':
				$data = $args[0];
				$chapterId = $data['chapterId'];
				$stmt = $db->prepare('SELECT book_id, chapter_order FROM chapters WHERE id = ?');
				$stmt->execute([$chapterId]);
				$chapter = $stmt->get_result()->fetch_assoc();
				if ($chapter) {
					$db->prepare('DELETE FROM chapters WHERE id = ?')->execute([$chapterId]);
					$db->prepare('UPDATE chapters SET chapter_order = chapter_order - 1 WHERE book_id = ? AND chapter_order > ?')->execute([$chapter['book_id'], $chapter['chapter_order']]);
				}
				$result = ['success' => true];
				break;
			case 'chapters:insert':
				$data = $args[0];
				$chapterId = $data['chapterId'];
				$direction = $data['direction'];
				$stmt = $db->prepare('SELECT book_id, chapter_order FROM chapters WHERE id = ?');
				$stmt->execute([$chapterId]);
				$ref = $stmt->get_result()->fetch_assoc();
				if ($ref) {
					$newOrder = $direction === 'above' ? $ref['chapter_order'] : $ref['chapter_order'] + 1;
					$db->prepare('UPDATE chapters SET chapter_order = chapter_order + 1 WHERE book_id = ? AND chapter_order >= ?')->execute([$ref['book_id'], $newOrder]);
					$db->prepare('INSERT INTO chapters (book_id, title, chapter_order, source_content, target_content) VALUES (?, ?, ?, ?, ?)')->execute([$ref['book_id'], 'New Chapter', $newOrder, '<p></p>', '<p></p>']);
				}
				$result = ['success' => true];
				break;
			case 'chapters:getTranslationContext':
				$data = $args[0];
				$chapterId = $data['chapterId'];
				$pairCount = $data['pairCount'];
				$selectedText = $data['selectedText'] ?? null;

				if ($pairCount <= 0) {
					$result = [];
					break;
				}

				$stmt = $db->prepare('SELECT book_id, chapter_order, source_content, target_content FROM chapters WHERE id = ?');
				$stmt->execute([$chapterId]);
				$current = $stmt->get_result()->fetch_assoc();
				if (!$current) {
					throw new Exception('Chapter not found.');
				}

				$currentPairs = extractMarkerPairsFromHtmlForContext($current['source_content'] ?? '', $current['target_content'] ?? '', $selectedText);

				if (count($currentPairs) >= $pairCount) {
					$result = array_slice($currentPairs, -$pairCount);
					break;
				}

				$needed = $pairCount - count($currentPairs);
				$stmt = $db->prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ? AND chapter_order < ? ORDER BY chapter_order DESC LIMIT 1');
				$stmt->execute([$current['book_id'], $current['chapter_order']]);
				$prev = $stmt->get_result()->fetch_assoc();

				if (!$prev) {
					$result = $currentPairs;
					break;
				}

				$prevPairs = extractMarkerPairsFromHtmlForContext($prev['source_content'] ?? '', $prev['target_content'] ?? '');
				$lastPrev = array_slice($prevPairs, -$needed);
				$result = array_merge($lastPrev, $currentPairs);
				break;

			// --- Documents ---
			case 'document:read':
				$filePath = $args[0];
				$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
				$result = $ext === 'docx' ? readDocx($filePath) : file_get_contents($filePath);
				break;
			case 'document:import':
				$data = $args[0];
				$stmt = $db->prepare('INSERT into user_books (user_id, title, source_language, target_language) VALUES (?, ?, ?, ?)');
				$stmt->execute([$userId, $data['title'], $data['source_language'], $data['target_language']]);
				$bookId = $db->insert_id;
				$chStmt = $db->prepare('INSERT INTO chapters (book_id, title, source_content, chapter_order) VALUES (?, ?, ?, ?)');
				foreach ($data['chapters'] as $i => $chapter) {
					$chStmt->execute([$bookId, $chapter['title'], $chapter['content'], $i + 1]);
				}

				// Generate cover automatically on import
				try {
					$promptPayload = [
						'model' => OPEN_ROUTER_MODEL,
						'messages' => [['role' => 'user', 'content' => "Using the book title \"{$data['title']}\", write a clear and simple description of a scene for an AI image generator to create a book cover. Include the setting, mood, and main objects. Include the \"{$data['title']}\" in the prompt Return the result as a JSON with one key \"prompt\"."]],
						'response_format' => ['type' => 'json_object'],
						'temperature' => 0.7
					];
					// MODIFIED: Passed $userApiKey
					$res = callOpenRouter($promptPayload, ['db' => $db, 'userId' => $userId, 'action' => 'generate_cover_prompt'], $userApiKey);
					$content = json_decode($res['choices'][0]['message']['content'] ?? '{}', true);
					$prompt = $content['prompt'] ?? null;

					if ($prompt) {
						$falPayload = ['prompt' => $prompt, 'image_size' => 'portrait_4_3'];
						$ch = curl_init('https://fal.run/fal-ai/qwen-image');
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_POST, true);
						curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($falPayload));
						curl_setopt($ch, CURLOPT_HTTPHEADER, [
							'Authorization: Key ' . FAL_API_KEY,
							'Content-Type: application/json',
							'Accept: application/json'
						]);
						$response = curl_exec($ch);
						curl_close($ch);

						$falData = $response ? json_decode($response, true) : [];

						if (isset($falData['images'][0]['url'])) {
							$localPaths = storeImageFromUrl($falData['images'][0]['url'], $bookId, 'cover-autogen');
							if ($localPaths) {
								$db->prepare('INSERT INTO images (user_id, book_id, image_local_path, thumbnail_local_path, image_type, prompt) VALUES (?, ?, ?, ?, ?, ?)')
									->execute([$userId, $bookId, $localPaths['original_path'], $localPaths['original_path'], 'generated', $prompt]);
							}
						}
					}
				} catch (Exception $e) {
					// Silently fail cover generation to not break import
					error_log("Auto-cover generation failed: " . $e->getMessage());
				}

				$result = ['success' => true, 'bookId' => $bookId];
				break;

			// --- AI & LLM ---
			case 'llm:process-text':
			case 'chat:send-message':
				$data = $args[0];
				$messages = $data['messages'] ?? [];
				if (isset($data['prompt'])) {
					$messages = [];
					if (!empty($data['prompt']['system'])) {
						$messages[] = ['role' => 'system', 'content' => $data['prompt']['system']];
					}
					if (!empty($data['prompt']['context_pairs'])) {
						$messages = array_merge($messages, $data['prompt']['context_pairs']);
					}
					if (!empty($data['prompt']['user'])) {
						$messages[] = ['role' => 'user', 'content' => $data['prompt']['user']];
					}
				}

				$bookId = $data['bookId'] ?? null;
				$tmContent = '';
				$codexContent = '';

				if ($bookId) {
					$hasTmPlaceholder = false;
					$hasCodexPlaceholder = false;
					foreach ($messages as $msg) {
						if ($msg['role'] === 'system') {
							if (strpos($msg['content'], '##TRANSLATION_MEMORY##') !== false) {
								$hasTmPlaceholder = true;
							}
							if (strpos($msg['content'], '##CODEX_BLOCK##') !== false) {
								$hasCodexPlaceholder = true;
							}
						}
					}

					if ($hasTmPlaceholder) {
						$lastUserMessage = '';
						for ($i = count($messages) - 1; $i >= 0; $i--) {
							if ($messages[$i]['role'] === 'user') {
								$lastUserMessage = $messages[$i]['content'];
								break;
							}
						}
						$words = array_filter(array_unique(preg_split('/[\s,.;:!?()"-]+/', strtolower($lastUserMessage))), fn($w) => mb_strlen($w) > 2);

						if (!empty($words)) {
							$tmPairs = [];
							$stmt = $db->prepare("SELECT tm.source_sentence, tm.target_sentence, b.source_language, b.target_language FROM user_books_translation_memory tm JOIN user_books b ON tm.book_id = b.id WHERE b.user_id = ? AND tm.source_sentence LIKE ? LIMIT 10");
							foreach ($words as $word) {
								$stmt->execute([$userId, "%$word%"]);
								$matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
								foreach ($matches as $m) {
									$key = $m['source_sentence'];
									if (!isset($tmPairs[$key])) {
										$tmPairs[$key] = $m;
										if (count($tmPairs) >= 100) {
											break 2;
										}
									}
								}
							}
							foreach ($tmPairs as $mem) {
								$tmContent .= "<{$mem['source_language']}>{$mem['source_sentence']}</{$mem['source_language']}>\n";
								$tmContent .= "<{$mem['target_language']}>{$mem['target_sentence']}</{$mem['target_language']}>\n";
							}
						}
					}

					if ($hasCodexPlaceholder) {
						$stmt = $db->prepare("SELECT codex_content FROM user_books WHERE id = ? AND user_id = ?");
						$stmt->execute([$bookId, $userId]);
						$row = $stmt->get_result()->fetch_assoc();
						if ($row && !empty($row['codex_content'])) {
							$codexContent = "Use the following glossary for consistent translation:\n<glossary>\n" . $row['codex_content'] . "\n</glossary>";
						}
					}

					foreach ($messages as &$msg) {
						if ($msg['role'] === 'system') {
							if ($hasTmPlaceholder) {
								if ($tmContent) {
									$msg['content'] = str_replace('##TRANSLATION_MEMORY##', $tmContent, $msg['content']);
								} else {
									$msg['content'] = preg_replace("/Use the following translation examples to guide the translation:\n##TRANSLATION_MEMORY##\n*/", '', $msg['content']);
								}
							}
							if ($hasCodexPlaceholder) {
								if ($codexContent) {
									$msg['content'] = str_replace('##CODEX_BLOCK##', $codexContent, $msg['content']);
								} else {
									$msg['content'] = str_replace("##CODEX_BLOCK##\n", '', $msg['content']);
								}
							}
							$msg['content'] = trim(preg_replace('/\n{3,}/', "\n\n", $msg['content']));
						}
					}
					unset($msg);
				}

				$payload = [
					'model' => $data['model'] ?? OPEN_ROUTER_MODEL,
					'messages' => $messages,
					'temperature' => $data['temperature'] ?? 0.7
				];
				$logCtx = ['db' => $db, 'userId' => $userId, 'action' => 'llm_process_text'];
				// MODIFIED: Passed $userApiKey
				$result = ['success' => true, 'data' => callOpenRouter($payload, $logCtx, $userApiKey)];
				break;
			case 'ai:getModels':
				$ch = curl_init('https://openrouter.ai/api/v1/models');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				$response = curl_exec($ch);
				curl_close($ch);

				$liveModelsData = $response ? json_decode($response, true) : [];

				$availableModelIds = array_flip(array_column($liveModelsData['data'] ?? [], 'id'));
				$staticGroupedModels = getStaticGroupedModels();
				$verifiedGroupedModels = [];
				foreach ($staticGroupedModels as $group) {
					$verifiedModelsInGroup = [];
					foreach ($group['models'] as $model) {
						if (isset($availableModelIds[$model['id']])) {
							$verifiedModelsInGroup[] = $model;
						}
					}
					if (!empty($verifiedModelsInGroup)) {
						$verifiedGroupedModels[] = [
							'group' => $group['group'],
							'models' => $verifiedModelsInGroup,
						];
					}
				}
				$result = ['success' => true, 'models' => $verifiedGroupedModels];
				break;
			case 'ai:generate-cover-prompt':
				$bookTitle = $args[0]['bookTitle'] ?? '';
				$prompt = "Using the book title \"$bookTitle\", write a clear and simple description of a scene for an AI image generator to create a book cover. Include the setting, mood, and main objects. Include the \"$bookTitle\" in the prompt Return the result as a JSON with one key \"prompt\". Example: with title \"Blue Scape\" {\"prompt\": \"An astronaut on a red planet looking at a big cosmic cloud, realistic, add the title \\\"Blue Scape\\\" to the image.\"}";
				$payload = [
					'model' => OPEN_ROUTER_MODEL,
					'messages' => [['role' => 'user', 'content' => $prompt]],
					'response_format' => ['type' => 'json_object'],
					'temperature' => 0.7
				];
				// MODIFIED: Passed $userApiKey
				$res = callOpenRouter($payload, ['db' => $db, 'userId' => $userId, 'action' => 'generate_cover_prompt'], $userApiKey);
				$content = json_decode($res['choices'][0]['message']['content'] ?? '{}', true);
				$result = ['success' => true, 'prompt' => $content['prompt'] ?? null];
				break;
			case 'ai:generate-cover':
				$bookId = $args[0]['bookId'];
				$prompt = $args[0]['prompt'];
				$falPayload = ['prompt' => $prompt, 'image_size' => 'portrait_4_3'];
				$ch = curl_init('https://fal.run/fal-ai/qwen-image');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($falPayload));
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Authorization: Key ' . FAL_API_KEY,
					'Content-Type: application/json',
					'Accept: application/json'
				]);
				$response = curl_exec($ch);
				curl_close($ch);

				if (!$response) {
					throw new Exception('Image generation API call failed.');
				}
				$falData = json_decode($response, true);
				if (!isset($falData['images'][0]['url'])) {
					throw new Exception('Image generation failed.');
				}

				$localPaths = storeImageFromUrl($falData['images'][0]['url'], $bookId, 'generated-fal');
				if (!$localPaths) {
					throw new Exception('Failed to save generated cover.');
				}

				$fullPath = '/userData/images/' . $localPaths['original_path'];
				$result = ['success' => true, 'filePath' => $fullPath, 'localPath' => $localPaths['original_path']];
				break;
			case 'log:translation':
				$data = $args[0];
				$marker = $data['marker'] ?? null;
				if ($marker && preg_match('/^\[\[#(\d+)\]\]$/', $marker, $matches)) {
					$marker = $matches[1];
				}
				$stmt = $db->prepare('INSERT INTO translation_logs (user_id, book_id, chapter_id, source_text, target_text, marker, model, temperature) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
				$stmt->execute([$userId, $data['bookId'], $data['chapterId'], $data['sourceText'], $data['targetText'], $marker, $data['model'], $data['temperature']]);
				$result = ['success' => true];
				break;

			// --- Dictionaries ---
			case 'dictionary:get':
				$bookId = $args[0];
				$path = DICTS_DIR . '/' . $bookId . '.json';
				$fileContent = file_exists($path) ? file_get_contents($path) : false;
				$result = $fileContent ? json_decode($fileContent, true) : [];
				break;
			case 'dictionary:save':
				$bookId = $args[0];
				$data = $args[1];
				file_put_contents(DICTS_DIR . '/' . $bookId . '.json', json_encode($data));
				$result = ['success' => true];
				break;
			case 'dictionary:getContentForAI':
				$bookId = $args[0];
				$type = $args[1];
				$path = DICTS_DIR . '/' . $bookId . '.json';
				$content = '';
				if (file_exists($path)) {
					$fileContent = file_get_contents($path);
					$entries = $fileContent ? (json_decode($fileContent, true) ?? []) : [];
					foreach ($entries as $entry) {
						if (!$type || ($entry['type'] ?? 'translation') === $type) {
							$content .= "{$entry['source']} = {$entry['target']}\n";
						}
					}
				}
				$result = $content;
				break;

			// --- Translation Memory ---
			case 'tm:getAll':
				$stmt = $db->prepare('SELECT n.id, n.title, n.author, n.source_language, n.target_language, (SELECT COUNT(*) from user_books_translation_memory WHERE book_id = n.id) as tm_count FROM user_books n WHERE n.user_id = ? ORDER BY n.updated_at DESC');
				$stmt->execute([$userId]);
				$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
				break;
			case 'tm:getDetails':
				$bookId = $args[0];
				$stmt = $db->prepare('SELECT source_sentence, target_sentence from user_books_translation_memory WHERE book_id = ? ORDER BY id ASC');
				$stmt->execute([$bookId]);
				$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
				break;
			case 'tm:delete':
				$bookId = $args[0];
				$db->prepare('DELETE from user_books_translation_memory WHERE book_id = ?')->execute([$bookId]);
				$db->prepare('UPDATE user_book_blocks SET is_analyzed = 0 WHERE book_id = ?')->execute([$bookId]);
				$result = ['success' => true];
				break;
			case 'translation-memory:start':
				$bookId = $args[0];
				$stmt = $db->prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ?');
				$stmt->execute([$bookId]);
				$chapters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
				$allPairs = [];
				foreach ($chapters as $ch) {
					$allPairs = array_merge($allPairs, extractAllMarkerPairs($ch['source_content'] ?? '', $ch['target_content'] ?? ''));
				}
				$db->prepare('DELETE from user_book_blocks WHERE book_id = ?')->execute([$bookId]);
				$stmt = $db->prepare('INSERT INTO user_book_blocks (book_id, marker_id, source_text, target_text, is_analyzed) VALUES (?, ?, ?, ?, 0)');
				foreach ($allPairs as $pair) {
					$stmt->execute([$bookId, $pair['marker'], $pair['source'], $pair['target']]);
				}
				$count = count($allPairs);
				if ($count > 0) {
					$db->prepare('INSERT INTO tm_generation_jobs (book_id, total_blocks) VALUES (?, ?)')->execute([$bookId, $count]);
					$result = ['job_id' => $db->insert_id, 'total_blocks' => $count];
				} else {
					$result = ['job_id' => null];
				}
				break;
			case 'translation-memory:process-batch':
				$jobId = $args[0];
				$stmt = $db->prepare('SELECT * FROM tm_generation_jobs WHERE id = ?');
				$stmt->execute([$jobId]);
				$job = $stmt->get_result()->fetch_assoc();
				if (!$job || $job['status'] === 'complete') {
					$result = ['status' => 'complete', 'processed_blocks' => $job['processed_blocks'] ?? 0];
					break;
				}
				$bookId = $job['book_id'];
				$stmt = $db->prepare('SELECT source_language, target_language FROM user_books WHERE id = ?');
				$stmt->execute([$bookId]);
				$book = $stmt->get_result()->fetch_assoc();
				$blockStmt = $db->prepare('SELECT * from user_book_blocks WHERE book_id = ? AND is_analyzed = 0 LIMIT 1');
				$blockStmt->execute([$bookId]);
				$block = $blockStmt->get_result()->fetch_assoc();

				if (!$block) {
					$db->prepare("UPDATE tm_generation_jobs SET status = 'complete' WHERE id = ?")->execute([$jobId]);
					$result = ['status' => 'complete', 'processed_blocks' => $job['total_blocks']];
				} else {
					$systemPrompt = "You are a literary translation analyst. Your task is to analyze a pair of texts—an original and its translation—and generate concise, actionable translation examples for an AI translator to imitate the style of the human translator. Return your response as a single JSON object with one key: 'pairs'. The value of 'pairs' must be an array of objects, where each object has two keys: 'source' and 'target'.";
					$userPrompt = "Analyze the following pair and generate exactly 2 translation pair(s) that best reflect the translator's style.\n\nSource ({$book['source_language']}):\n{$block['source_text']}\n\nTranslation ({$book['target_language']}):\n{$block['target_text']}";

					$payload = [
						'model' => OPEN_ROUTER_MODEL,
						'messages' => [
							['role' => 'system', 'content' => $systemPrompt],
							['role' => 'user', 'content' => $userPrompt]
						],
						'temperature' => 0.7,
						'response_format' => ['type' => 'json_object']
					];

					// MODIFIED: Passed $userApiKey
					$aiResponse = callOpenRouter($payload, ['db' => $db, 'userId' => $userId, 'action' => 'tm_llm_call'], $userApiKey);
					$content = json_decode($aiResponse['choices'][0]['message']['content'] ?? '{}', true);

					if (isset($content['pairs'])) {
						$insertStmt = $db->prepare('INSERT INTO user_books_translation_memory (book_id, block_id, source_sentence, target_sentence) VALUES (?, ?, ?, ?)');
						foreach ($content['pairs'] as $pair) {
							$insertStmt->execute([$bookId, $block['id'], $pair['source'], $pair['target']]);
						}
					}

					$db->prepare('UPDATE user_book_blocks SET is_analyzed = 1 WHERE id = ?')->execute([$block['id']]);
					$db->prepare('UPDATE tm_generation_jobs SET processed_blocks = processed_blocks + 1 WHERE id = ?')->execute([$jobId]);
					$result = ['status' => 'running', 'processed_blocks' => $job['processed_blocks'] + 1, 'total_blocks' => $job['total_blocks']];
				}
				break;

			// --- Codex ---
			case 'codex:getAll':
				$stmt = $db->prepare('SELECT id, title, author, source_language, target_language, codex_status FROM user_books WHERE user_id = ? ORDER BY updated_at DESC');
				$stmt->execute([$userId]);
				$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
				break;
			case 'codex:getDetails':
				$bookId = $args[0];
				$stmt = $db->prepare('SELECT id, title, codex_content, codex_status FROM user_books WHERE id = ? AND user_id = ?');
				$stmt->execute([$bookId, $userId]);
				$result = $stmt->get_result()->fetch_assoc();
				break;
			case 'codex:save':
				$bookId = $args[0];
				$content = $args[1];
				$db->prepare('UPDATE user_books SET codex_content = ? WHERE id = ? AND user_id = ?')->execute([$content, $bookId, $userId]);
				$result = ['success' => true];
				break;
			case 'codex:reset':
				$bookId = $args[0];
				$db->prepare("UPDATE user_books SET codex_content = NULL, codex_status = 'none', codex_chunks_total = 0, codex_chunks_processed = 0 WHERE id = ? AND user_id = ?")->execute([$bookId, $userId]);
				$result = ['success' => true];
				break;
			case 'codex:start':
				$bookId = $args[0];
				$stmt = $db->prepare('SELECT source_content FROM chapters WHERE book_id = ?');
				$stmt->execute([$bookId]);
				$chapters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
				$fullText = '';
				foreach ($chapters as $c) {
					$fullText .= htmlToPlainText($c['source_content'] ?? '') . "\n";
				}
				$words = preg_split('/\s+/', $fullText);
				$chunks = array_chunk($words, 8000);
				$totalChunks = count($chunks);

				$db->prepare('DELETE FROM user_book_codex_chunks WHERE book_id = ?')->execute([$bookId]);
				$stmt = $db->prepare('INSERT INTO user_book_codex_chunks (book_id, chunk_index, chunk_text, is_processed) VALUES (?, ?, ?, 0)');
				foreach ($chunks as $i => $chunk) {
					$stmt->execute([$bookId, $i, implode(' ', $chunk)]);
				}

				$db->prepare("UPDATE user_books SET codex_status = 'generating', codex_chunks_total = ?, codex_chunks_processed = 0 WHERE id = ?")->execute([$totalChunks, $bookId]);
				$result = ['status' => 'generating'];
				break;
			case 'codex:process-batch':
				$bookId = $args[0];
				$stmt = $db->prepare('SELECT * FROM user_books WHERE id = ?');
				$stmt->execute([$bookId]);
				$book = $stmt->get_result()->fetch_assoc();

				$chunkStmt = $db->prepare('SELECT * FROM user_book_codex_chunks WHERE book_id = ? AND is_processed = 0 ORDER BY chunk_index ASC LIMIT 1');
				$chunkStmt->execute([$bookId]);
				$chunk = $chunkStmt->get_result()->fetch_assoc();

				if (!$chunk) {
					$db->prepare("UPDATE user_books SET codex_status = 'complete' WHERE id = ?")->execute([$bookId]);
					$result = ['status' => 'complete'];
				} else {
					$systemPrompt = "You are a meticulous world-building assistant for a bookist. Your task is to maintain a codex (an encyclopedia of the world). Identify new characters, locations, or lore from the text chunk and integrate them. Your output must be the complete, updated codex in {$book['target_language']}.";
					$userPrompt = "**Existing Codex Content:**\n<codex>\n" . ($book['codex_content'] ?? 'This is the beginning of the codex.') . "\n</codex>\n\n**Text Chunk to Analyze (in {$book['source_language']}):**\n<text>\n{$chunk['chunk_text']}\n</text>";

					$payload = [
						'model' => OPEN_ROUTER_MODEL,
						'messages' => [
							['role' => 'system', 'content' => $systemPrompt],
							['role' => 'user', 'content' => $userPrompt]
						],
						'temperature' => 0.5
					];

					// MODIFIED: Passed $userApiKey
					$aiResponse = callOpenRouter($payload, ['db' => $db, 'userId' => $userId, 'action' => 'codex_llm_call'], $userApiKey);
					$updatedCodexText = trim($aiResponse['choices'][0]['message']['content'] ?? '');

					if ($updatedCodexText) {
						$db->prepare('UPDATE user_books SET codex_content = ?, codex_chunks_processed = codex_chunks_processed + 1 WHERE id = ?')->execute([$updatedCodexText, $bookId]);
					}
					$db->prepare('UPDATE user_book_codex_chunks SET is_processed = 1 WHERE id = ?')->execute([$chunk['id']]);
					$result = ['status' => 'generating', 'processed' => $book['codex_chunks_processed'] + 1, 'total' => $book['codex_chunks_total']];
				}
				break;

			default:
				throw new Exception("Channel '$channel' not implemented in PHP backend.");
		}

		echo json_encode(['success' => true, 'data' => $result]);

	} catch (Exception $e) {
		echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	}
