<?php

	declare(strict_types=1);

	require_once __DIR__ . '/config.php';
	require_once __DIR__ . '/db.php';
	require_once __DIR__ . '/utils.php';

	session_start();

	header('Content-Type: application/json');

	$input = json_decode(file_get_contents('php://input'), true);
	$channel = $input['channel'] ?? '';
	$args = $input['args'] ?? [];

	$db = getDB();
	$userId = $_SESSION['user']['id'] ?? 1;

	try {
		$result = null;

		switch ($channel) {
			// --- System & Auth ---
			case 'splash:get-init-data':
				$result = ['version' => APP_VERSION, 'user' => $_SESSION['user'] ?? null, 'websiteUrl' => '#'];
				break;
			case 'auth:login':
				$creds = $args[0];
				$stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
				$stmt->execute([$creds['username']]);
				$user = $stmt->fetch();
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
				if ($stmt->fetch()) {
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
						$merged[$key] = json_decode(file_get_contents($file), true);
					}
				}
				$result = json_encode($merged);
				break;

			// --- API Logs ---
			case 'logs:get':
				$page = max(1, (int)($args[0] ?? 1));
				$limit = 25;
				$offset = ($page - 1) * $limit;
				$total = $db->prepare('SELECT COUNT(*) FROM api_logs WHERE user_id = ?');
				$total->execute([$userId]);
				$count = $total->fetchColumn();

				$stmt = $db->prepare('SELECT id, action, request_payload, response_body, response_code, created_at FROM api_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
				$stmt->execute([$userId, $limit, $offset]);
				$logs = $stmt->fetchAll();
				$result = ['logs' => $logs, 'totalPages' => ceil($count / $limit), 'currentPage' => $page];
				break;

			// --- Languages ---
			case 'languages:get-supported':
				$result = [
					'en-US' => 'English (US)', 'tr' => 'Turkish', 'no' => 'Norwegian', 'es-ES' => 'Spanish'
					// Add remaining languages as needed...
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
				$books = $stmt->fetchAll();
				foreach ($books as &$book) {
					$chStmt = $db->prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ?');
					$chStmt->execute([$book['id']]);
					$chapters = $chStmt->fetchAll();
					$book['source_word_count'] = array_sum(array_map(fn($c) => countWordsInHtml($c['source_content'] ?? ''), $chapters));
					$book['target_word_count'] = array_sum(array_map(fn($c) => countWordsInHtml($c['target_content'] ?? ''), $chapters));
					if ($book['cover_path']) {
						$book['cover_path'] = '/userData/images/' . $book['cover_path'];
					}
				}
				$result = $books;
				break;
			case 'books:getOne':
			case 'books:getFullManuscript':
				$bookId = $args[0];
				$stmt = $db->prepare('SELECT * FROM user_books WHERE id = ? AND user_id = ?');
				$stmt->execute([$bookId, $userId]);
				$book = $stmt->fetch();
				if ($book) {
					$chStmt = $db->prepare('SELECT * FROM chapters WHERE book_id = ? ORDER BY chapter_order');
					$chStmt->execute([$bookId]);
					$book['chapters'] = $chStmt->fetchAll();
					foreach ($book['chapters'] as &$chapter) {
						$chapter['source_word_count'] = countWordsInHtml($chapter['source_content'] ?? '');
						$chapter['target_word_count'] = countWordsInHtml($chapter['target_content'] ?? '');
					}
				}
				$result = $book;
				break;
			case 'books:createBlank':
				$data = $args[0];
				$stmt = $db->prepare('INSERT into user_books (user_id, title, source_language, target_language) VALUES (?, ?, ?, ?)');
				$stmt->execute([$userId, $data['title'], $data['source_language'], $data['target_language']]);
				$bookId = $db->lastInsertId();
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
				$field = $data['promptType'] . '_settings';
				$stmt = $db->prepare("UPDATE user_books SET $field = ? WHERE id = ? AND user_id = ?");
				$stmt->execute([json_encode($data['settings']), $data['bookId'], $userId]);
				$result = ['success' => true];
				break;
			case 'books:delete':
				$bookId = $args[0];
				$stmt = $db->prepare('DELETE FROM user_books WHERE id = ? AND user_id = ?');
				$stmt->execute([$bookId, $userId]);
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

			// --- Chapters ---
			case 'chapters:updateField':
				$data = $args[0];
				$stmt = $db->prepare("UPDATE chapters SET {$data['field']} = ? WHERE id = ?");
				$stmt->execute([$data['value'], $data['chapterId']]);
				$result = ['success' => true];
				break;
			case 'chapters:getRawContent':
				$data = $args[0];
				$stmt = $db->prepare("SELECT {$data['field']} FROM chapters WHERE id = ?");
				$stmt->execute([$data['chapterId']]);
				$result = $stmt->fetchColumn();
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
				$bookId = $db->lastInsertId();
				$chStmt = $db->prepare('INSERT INTO chapters (book_id, title, source_content, chapter_order) VALUES (?, ?, ?, ?)');
				foreach ($data['chapters'] as $i => $chapter) {
					$chStmt->execute([$bookId, $chapter['title'], $chapter['content'], $i + 1]);
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
					if (!empty($data['prompt']['system'])) $messages[] = ['role' => 'system', 'content' => $data['prompt']['system']];
					if (!empty($data['prompt']['context_pairs'])) $messages = array_merge($messages, $data['prompt']['context_pairs']);
					if (!empty($data['prompt']['user'])) $messages[] = ['role' => 'user', 'content' => $data['prompt']['user']];
				}
				$payload = [
					'model' => $data['model'] ?? OPEN_ROUTER_MODEL,
					'messages' => $messages,
					'temperature' => $data['temperature'] ?? 0.7
				];
				$logCtx = ['db' => $db, 'userId' => $userId, 'action' => 'llm_process_text'];
				$result = ['success' => true, 'data' => callOpenRouter($payload, $logCtx)];
				break;
			case 'ai:getModels':
				$ch = curl_init('https://openrouter.ai/api/v1/models');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				$response = curl_exec($ch);
				//curl_close($ch);
				$models = json_decode($response, true)['data'] ?? [];
				$result = ['success' => true, 'models' => [['group' => 'Available Models', 'models' => array_slice($models, 0, 20)]]];
				break;
			case 'log:translation':
				$data = $args[0];
				$stmt = $db->prepare('INSERT INTO translation_logs (user_id, book_id, chapter_id, source_text, target_text, marker, model, temperature) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
				$stmt->execute([$userId, $data['bookId'], $data['chapterId'], $data['sourceText'], $data['targetText'], $data['marker'], $data['model'], $data['temperature']]);
				$result = ['success' => true];
				break;

			// --- Dictionaries ---
			case 'dictionary:get':
				$bookId = $args[0];
				$path = DICTS_DIR . '/' . $bookId . '.json';
				$result = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
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
					$entries = json_decode(file_get_contents($path), true) ?? [];
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
				$result = $stmt->fetchAll();
				break;
			case 'tm:getDetails':
				$bookId = $args[0];
				$stmt = $db->prepare('SELECT source_sentence, target_sentence from user_books_translation_memory WHERE book_id = ? ORDER BY id ASC');
				$stmt->execute([$bookId]);
				$result = $stmt->fetchAll();
				break;
			case 'tm:delete':
				$bookId = $args[0];
				$db->prepare('DELETE from user_books_translation_memory WHERE book_id = ?')->execute([$bookId]);
				$db->prepare('UPDATE translation_memory_blocks SET is_analyzed = 0 WHERE book_id = ?')->execute([$bookId]);
				$result = ['success' => true];
				break;
			case 'translation-memory:start':
				$bookId = $args[0];
				$chapters = $db->prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ?')->fetchAll();
				$allPairs = [];
				foreach ($chapters as $ch) {
					$allPairs = array_merge($allPairs, extractAllMarkerPairs($ch['source_content'] ?? '', $ch['target_content'] ?? ''));
				}
				$db->prepare('DELETE from user_books_translation_memory_blocks WHERE book_id = ?')->execute([$bookId]);
				$stmt = $db->prepare('INSERT INTO translation_memory_blocks (book_id, marker_id, source_text, target_text, is_analyzed) VALUES (?, ?, ?, ?, 0)');
				foreach ($allPairs as $pair) {
					$stmt->execute([$bookId, $pair['marker'], $pair['source'], $pair['target']]);
				}
				$count = count($allPairs);
				if ($count > 0) {
					$db->prepare('INSERT INTO tm_jobs (book_id, total_blocks) VALUES (?, ?)')->execute([$bookId, $count]);
					$result = ['job_id' => $db->lastInsertId(), 'total_blocks' => $count];
				} else {
					$result = ['job_id' => null];
				}
				break;
			case 'translation-memory:process-batch':
				$jobId = $args[0];
				$job = $db->prepare('SELECT * FROM tm_jobs WHERE id = ?')->execute([$jobId]);
				$job = $job->fetch();
				if (!$job || $job['status'] === 'complete') {
					$result = ['status' => 'complete', 'processed_blocks' => $job['processed_blocks'] ?? 0];
					break;
				}
				$bookId = $job['book_id'];
				$book = $db->prepare('SELECT source_language, target_language FROM user_books WHERE id = ?')->execute([$bookId])->fetch();
				$blockStmt = $db->prepare('SELECT * from user_books_translation_memory_blocks WHERE book_id = ? AND is_analyzed = 0 LIMIT 1');
				$blockStmt->execute([$bookId]);
				$block = $blockStmt->fetch();

				if (!$block) {
					$db->prepare("UPDATE tm_jobs SET status = 'complete' WHERE id = ?")->execute([$jobId]);
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

					$aiResponse = callOpenRouter($payload, ['db' => $db, 'userId' => $userId, 'action' => 'tm_llm_call']);
					$content = json_decode($aiResponse['choices'][0]['message']['content'] ?? '{}', true);

					if (isset($content['pairs'])) {
						$insertStmt = $db->prepare('INSERT INTO translation_memory (book_id, block_id, source_sentence, target_sentence) VALUES (?, ?, ?, ?)');
						foreach ($content['pairs'] as $pair) {
							$insertStmt->execute([$bookId, $block['id'], $pair['source'], $pair['target']]);
						}
					}

					$db->prepare('UPDATE translation_memory_blocks SET is_analyzed = 1 WHERE id = ?')->execute([$block['id']]);
					$db->prepare('UPDATE tm_jobs SET processed_blocks = processed_blocks + 1 WHERE id = ?')->execute([$jobId]);
					$result = ['status' => 'running', 'processed_blocks' => $job['processed_blocks'] + 1, 'total_blocks' => $job['total_blocks']];
				}
				break;

			// --- Codex ---
			case 'codex:getAll':
				$stmt = $db->prepare('SELECT id, title, author, source_language, target_language, codex_status FROM user_books WHERE user_id = ? ORDER BY updated_at DESC');
				$stmt->execute([$userId]);
				$result = $stmt->fetchAll();
				break;
			case 'codex:getDetails':
				$bookId = $args[0];
				$stmt = $db->prepare('SELECT id, title, codex_content, codex_status FROM user_books WHERE id = ? AND user_id = ?');
				$stmt->execute([$bookId, $userId]);
				$result = $stmt->fetch();
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
				$chapters = $db->prepare('SELECT source_content FROM chapters WHERE book_id = ?')->fetchAll();
				$fullText = '';
				foreach($chapters as $c) $fullText .= htmlToPlainText($c['source_content'] ?? '') . "\n";
				$words = preg_split('/\s+/', $fullText);
				$chunks = array_chunk($words, 8000);
				$totalChunks = count($chunks);

				$db->prepare('DELETE FROM codex_chunks WHERE book_id = ?')->execute([$bookId]);
				$stmt = $db->prepare('INSERT INTO codex_chunks (book_id, chunk_index, chunk_text, is_processed) VALUES (?, ?, ?, 0)');
				foreach($chunks as $i => $chunk) {
					$stmt->execute([$bookId, $i, implode(' ', $chunk)]);
				}

				$db->prepare("UPDATE user_books SET codex_status = 'generating', codex_chunks_total = ?, codex_chunks_processed = 0 WHERE id = ?")->execute([$totalChunks, $bookId]);
				$result = ['status' => 'generating'];
				break;
			case 'codex:process-batch':
				$bookId = $args[0];
				$book = $db->prepare('SELECT * FROM user_books WHERE id = ?')->execute([$bookId])->fetch();
				$chunkStmt = $db->prepare('SELECT * FROM codex_chunks WHERE book_id = ? AND is_processed = 0 ORDER BY chunk_index ASC LIMIT 1');
				$chunkStmt->execute([$bookId]);
				$chunk = $chunkStmt->fetch();

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

					$aiResponse = callOpenRouter($payload, ['db' => $db, 'userId' => $userId, 'action' => 'codex_llm_call']);
					$updatedCodexText = trim($aiResponse['choices'][0]['message']['content'] ?? '');

					if ($updatedCodexText) {
						$db->prepare('UPDATE user_books SET codex_content = ?, codex_chunks_processed = codex_chunks_processed + 1 WHERE id = ?')->execute([$updatedCodexText, $bookId]);
					}
					$db->prepare('UPDATE codex_chunks SET is_processed = 1 WHERE id = ?')->execute([$chunk['id']]);
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
