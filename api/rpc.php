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
	$userId = $_SESSION['user']['id'] ?? 1; // Default to 1 if auth is bypassed for local testing

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

			// --- Novels ---
			case 'novels:getAllWithCovers':
				$stmt = $db->prepare("
                SELECT n.*, i.image_local_path as cover_path, 
                (SELECT COUNT(id) FROM chapters WHERE novel_id = n.id) as chapter_count
                FROM novels n
                LEFT JOIN images i ON n.id = i.novel_id AND i.image_type LIKE '%cover%'
                WHERE n.user_id = ? ORDER BY n.updated_at DESC
            ");
				$stmt->execute([$userId]);
				$novels = $stmt->fetchAll();
				foreach ($novels as &$novel) {
					$chStmt = $db->prepare('SELECT source_content, target_content FROM chapters WHERE novel_id = ?');
					$chStmt->execute([$novel['id']]);
					$chapters = $chStmt->fetchAll();
					$novel['source_word_count'] = array_sum(array_map(fn($c) => countWordsInHtml($c['source_content'] ?? ''), $chapters));
					$novel['target_word_count'] = array_sum(array_map(fn($c) => countWordsInHtml($c['target_content'] ?? ''), $chapters));
					if ($novel['cover_path']) {
						$novel['cover_path'] = '/userData/images/' . $novel['cover_path'];
					}
				}
				$result = $novels;
				break;
			case 'novels:getOne':
			case 'novels:getFullManuscript':
				$novelId = $args[0];
				$stmt = $db->prepare('SELECT * FROM novels WHERE id = ? AND user_id = ?');
				$stmt->execute([$novelId, $userId]);
				$novel = $stmt->fetch();
				if ($novel) {
					$chStmt = $db->prepare('SELECT * FROM chapters WHERE novel_id = ? ORDER BY chapter_order');
					$chStmt->execute([$novelId]);
					$novel['chapters'] = $chStmt->fetchAll();
					foreach ($novel['chapters'] as &$chapter) {
						$chapter['source_word_count'] = countWordsInHtml($chapter['source_content'] ?? '');
						$chapter['target_word_count'] = countWordsInHtml($chapter['target_content'] ?? '');
					}
				}
				$result = $novel;
				break;
			case 'novels:createBlank':
				$data = $args[0];
				$stmt = $db->prepare('INSERT INTO novels (user_id, title, source_language, target_language) VALUES (?, ?, ?, ?)');
				$stmt->execute([$userId, $data['title'], $data['source_language'], $data['target_language']]);
				$novelId = $db->lastInsertId();
				$chStmt = $db->prepare('INSERT INTO chapters (novel_id, title, chapter_order, source_content, target_content) VALUES (?, ?, ?, ?, ?)');
				for ($i = 1; $i <= 10; $i++) {
					$chStmt->execute([$novelId, "Chapter $i", $i, '<p></p>', '<p></p>']);
				}
				$result = ['success' => true, 'novelId' => $novelId];
				break;
			case 'novels:updateField':
			case 'novels:updateMeta':
				$data = $args[0];
				$stmt = $db->prepare('UPDATE novels SET title = ?, author = ? WHERE id = ? AND user_id = ?');
				$stmt->execute([$data['title'], $data['author'], $data['novelId'], $userId]);
				$result = ['success' => true];
				break;
			case 'novels:updateProseSettings':
				$data = $args[0];
				$stmt = $db->prepare('UPDATE novels SET source_language = ?, target_language = ? WHERE id = ? AND user_id = ?');
				$stmt->execute([$data['source_language'], $data['target_language'], $data['novelId'], $userId]);
				$result = ['success' => true];
				break;
			case 'novels:updatePromptSettings':
				$data = $args[0];
				$field = $data['promptType'] . '_settings';
				$stmt = $db->prepare("UPDATE novels SET $field = ? WHERE id = ? AND user_id = ?");
				$stmt->execute([json_encode($data['settings']), $data['novelId'], $userId]);
				$result = ['success' => true];
				break;
			case 'novels:delete':
				$novelId = $args[0];
				$stmt = $db->prepare('DELETE FROM novels WHERE id = ? AND user_id = ?');
				$stmt->execute([$novelId, $userId]);
				$result = ['success' => true];
				break;
			case 'novels:exportToDocx':
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
				$stmt = $db->prepare('INSERT INTO novels (user_id, title, source_language, target_language) VALUES (?, ?, ?, ?)');
				$stmt->execute([$userId, $data['title'], $data['source_language'], $data['target_language']]);
				$novelId = $db->lastInsertId();
				$chStmt = $db->prepare('INSERT INTO chapters (novel_id, title, source_content, chapter_order) VALUES (?, ?, ?, ?)');
				foreach ($data['chapters'] as $i => $chapter) {
					$chStmt->execute([$novelId, $chapter['title'], $chapter['content'], $i + 1]);
				}
				$result = ['success' => true, 'novelId' => $novelId];
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
				$result = ['success' => true, 'data' => callOpenRouter($payload)];
				break;
			case 'ai:getModels':
				$ch = curl_init('https://openrouter.ai/api/v1/models');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$response = curl_exec($ch);
				curl_close($ch);
				$models = json_decode($response, true)['data'] ?? [];
				// Simple grouping for UI
				$result = ['success' => true, 'models' => [['group' => 'Available Models', 'models' => array_slice($models, 0, 20)]]];
				break;

			// --- Dictionaries ---
			case 'dictionary:get':
				$novelId = $args[0];
				$path = DICTS_DIR . '/' . $novelId . '.json';
				$result = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
				break;
			case 'dictionary:save':
				$novelId = $args[0];
				$data = $args[1];
				file_put_contents(DICTS_DIR . '/' . $novelId . '.json', json_encode($data));
				$result = ['success' => true];
				break;
			case 'dictionary:getContentForAI':
				$novelId = $args[0];
				$type = $args[1];
				$path = DICTS_DIR . '/' . $novelId . '.json';
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

			// --- Background Tasks (Polling Replacements) ---
			case 'translation-memory:start':
				$novelId = $args[0];
				$stmt = $db->prepare('SELECT COUNT(*) FROM translation_memory_blocks WHERE novel_id = ? AND is_analyzed = 0');
				$stmt->execute([$novelId]);
				$count = $stmt->fetchColumn();
				if ($count > 0) {
					$db->prepare('INSERT INTO tm_jobs (novel_id, total_blocks) VALUES (?, ?)')->execute([$novelId, $count]);
					$result = ['job_id' => $db->lastInsertId(), 'total_blocks' => $count];
				} else {
					$result = ['job_id' => null];
				}
				break;
			case 'translation-memory:process-batch':
				$jobId = $args[0];
				$stmt = $db->prepare('SELECT * FROM tm_jobs WHERE id = ?');
				$stmt->execute([$jobId]);
				$job = $stmt->fetch();
				if (!$job || $job['status'] === 'complete') {
					$result = ['status' => 'complete', 'processed_blocks' => $job['processed_blocks'] ?? 0];
					break;
				}
				// Mock processing 1 block for demonstration (In reality, call LLM here)
				$db->prepare('UPDATE tm_jobs SET processed_blocks = processed_blocks + 1 WHERE id = ?')->execute([$jobId]);
				if ($job['processed_blocks'] + 1 >= $job['total_blocks']) {
					$db->prepare("UPDATE tm_jobs SET status = 'complete' WHERE id = ?")->execute([$jobId]);
					$result = ['status' => 'complete', 'processed_blocks' => $job['total_blocks']];
				} else {
					$result = ['status' => 'running', 'processed_blocks' => $job['processed_blocks'] + 1, 'total_blocks' => $job['total_blocks']];
				}
				break;

			case 'codex:start':
				$novelId = $args[0];
				$db->prepare("UPDATE novels SET codex_status = 'generating', codex_chunks_total = 5, codex_chunks_processed = 0 WHERE id = ?")->execute([$novelId]);
				$result = ['status' => 'generating'];
				break;
			case 'codex:process-batch':
				$novelId = $args[0];
				$stmt = $db->prepare('SELECT codex_status, codex_chunks_total, codex_chunks_processed FROM novels WHERE id = ?');
				$stmt->execute([$novelId]);
				$novel = $stmt->fetch();
				if (!$novel || $novel['codex_status'] === 'complete') {
					$result = ['status' => 'complete'];
					break;
				}
				$db->prepare('UPDATE novels SET codex_chunks_processed = codex_chunks_processed + 1 WHERE id = ?')->execute([$novelId]);
				if ($novel['codex_chunks_processed'] + 1 >= $novel['codex_chunks_total']) {
					$db->prepare("UPDATE novels SET codex_status = 'complete' WHERE id = ?")->execute([$novelId]);
					$result = ['status' => 'complete'];
				} else {
					$result = ['status' => 'generating', 'processed' => $novel['codex_chunks_processed'] + 1, 'total' => $novel['codex_chunks_total']];
				}
				break;

			default:
				// MODIFIED: Throw an exception so the frontend rpcInvoke catches it as an error
				throw new Exception("Channel '$channel' not implemented in PHP backend.");
		}

		// MODIFIED: Always wrap the successful handler result in 'data'.
		// This perfectly mirrors Electron's ipcMain.handle returning data to ipcRenderer.invoke,
		// preventing 'undefined' errors when the frontend expects an object with a 'success' property.
		echo json_encode(['success' => true, 'data' => $result]);

	} catch (Exception $e) {
		echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	}
