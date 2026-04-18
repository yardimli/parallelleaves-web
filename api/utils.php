<?php

	declare(strict_types=1);

	function htmlToPlainText(string $html): string
	{
		if (!$html) {
			return '';
		}
		$s = preg_replace('/<br\s*\/?>/i', "\n", $html);
		$block = '(?:p|div|section|article|header|footer|nav|aside|h[1-6]|ul|ol|li|table|thead|tbody|tfoot|tr|th|td|blockquote|pre|hr)';
		$s = preg_replace("/<\s*{$block}[^>]*>/i", "\n", $s);
		$s = preg_replace("/<\/\s*{$block}\s*>/i", "\n", $s);
		$s = strip_tags($s);
		$s = preg_replace('/\s+([.,!?;:])/', '$1', $s);
		$s = preg_replace('/(\() +/', '$1', $s);
		$s = preg_replace('/ +(\))/', '$1', $s);
		$s = preg_replace('/[ \t]+\n/', "\n", $s);
		$s = preg_replace('/\n[ \t]+/', "\n", $s);
		$s = preg_replace('/\n{3,}/', "\n\n", $s);
		$s = preg_replace('/[ \t]{2,}/', ' ', $s);
		return trim($s);
	}

	function countWordsInHtml(string $html): int
	{
		if (!$html) {
			return 0;
		}
		$text = htmlToPlainText($html);
		$words = array_filter(preg_split('/\s+/', trim($text)));
		return count($words);
	}

// MODIFIED: Rewritten to use PHPWord to preserve text and paragraph formatting
	function readDocx(string $filePath): string
	{
		try {
			$phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
			$htmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');

			// Save HTML to a temporary file and read it back
			$tempFile = tempnam(sys_get_temp_dir(), 'docx_html');
			$htmlWriter->save($tempFile);
			$html = file_get_contents($tempFile);
			unlink($tempFile);

			// Extract only the body content to avoid injecting <html> and <head> tags into the frontend
			if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
				$html = $matches[1];
			}

			return trim($html);
		} catch (Exception $e) {
			error_log('PHPWord Error: ' . $e->getMessage());
			return "";
		}
	}

// Log API interactions to the database
	function logInteraction(PDO $db, int $userId, string $action, ?array $requestPayload, string $responseBody, int $responseCode): void
	{
		try {
			$stmt = $db->prepare('INSERT INTO api_logs (user_id, action, request_payload, response_body, response_code) VALUES (?, ?, ?, ?, ?)');
			$payloadJson = $requestPayload ? json_encode($requestPayload, JSON_UNESCAPED_UNICODE) : null;
			$stmt->execute([$userId, $action, $payloadJson, $responseBody, $responseCode]);
		} catch (Exception $e) {
			error_log('Failed to write to database log: ' . $e->getMessage());
		}
	}

// Call OpenRouter API
	function callOpenRouter(array $payload, ?array $logContext = null): array
	{
		$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . OPEN_ROUTER_API_KEY,
			'HTTP-Referer: https://paralleleaves.com',
			'X-Title: Parallel Leaves',
			'Content-Type: application/json'
		]);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($logContext && isset($logContext['db'], $logContext['userId'], $logContext['action'])) {
			logInteraction($logContext['db'], $logContext['userId'], $logContext['action'], $payload, (string)$response, $httpCode);
		}

		return json_decode((string)$response, true) ?? [];
	}

// Extract translation pairs from HTML for Translation Memory
	function extractAllMarkerPairs(string $sourceHtml, string $targetHtml): array
	{
		$getSegments = function ($html) {
			$segments = [];
			if (preg_match_all('/\[\[#(\d+)\]\](.*?)\{\{#\1\}\}/s', $html, $matches, PREG_SET_ORDER)) {
				foreach ($matches as $match) {
					$number = (int)$match[1];
					$content = preg_replace('/(\[\[#\d+\]\])|(\{\{#\d+\}\})/', '', $match[2]);
					$plainText = trim(htmlToPlainText($content));
					if ($plainText) {
						$segments[] = ['number' => $number, 'text' => $plainText];
					}
				}
			}
			return $segments;
		};

		$sourceSegments = $getSegments($sourceHtml);
		$targetSegments = $getSegments($targetHtml);

		$sourceMap = [];
		foreach ($sourceSegments as $s) {
			$sourceMap[$s['number']] = $s['text'];
		}

		$pairs = [];
		foreach ($targetSegments as $t) {
			if (isset($sourceMap[$t['number']])) {
				$pairs[] = [
					'marker' => $t['number'],
					'source' => $sourceMap[$t['number']],
					'target' => $t['text']
				];
			}
		}

		usort($pairs, fn($a, $b) => $a['marker'] <=> $b['marker']);
		return $pairs;
	}

	function extractMarkerPairsFromHtmlForContext(string $sourceHtml, string $targetHtml, ?string $selectedText = null): array
	{
		$pairs = extractAllMarkerPairs($sourceHtml, $targetHtml);

		if ($selectedText && count($pairs) > 0) {
			$lastIdx = count($pairs) - 1;
			$lastText = $pairs[$lastIdx]['source'];
			$selIdx = mb_strpos($lastText, trim($selectedText));
			if ($selIdx !== false) {
				$pairs[$lastIdx]['source'] = trim(mb_substr($lastText, 0, $selIdx));
				if (empty($pairs[$lastIdx]['source'])) {
					array_pop($pairs);
				}
			}
		}

		return $pairs;
	}

	function findHighestMarkerNumber(string $sourceHtml, string $targetHtml): int
	{
		$regex = '/\[\[#(\d+)\]\]|\{\{#(\d+)\}\}/';
		$combined = $sourceHtml . $targetHtml;
		preg_match_all($regex, $combined, $matches, PREG_SET_ORDER);
		$highest = 0;
		foreach ($matches as $match) {
			$num = (int)($match[1] ?: $match[2]);
			if ($num > $highest) {
				$highest = $num;
			}
		}
		return $highest;
	}

	function storeImageFromUrl(string $url, int $bookId, string $filenameBase): ?array
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$data = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode !== 200 || !$data) {
			return null;
		}

		$bookDir = IMAGES_DIR . '/books/' . $bookId;
		if (!is_dir($bookDir)) {
			mkdir($bookDir, 0755, true);
		}

		$ext = 'png';
		$pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));
		if (isset($pathInfo['extension']) && in_array(strtolower($pathInfo['extension']), ['png', 'jpg', 'jpeg', 'webp'])) {
			$ext = strtolower($pathInfo['extension']);
		}

		$filename = $filenameBase . '-' . time() . '.' . $ext;
		$localPath = $bookDir . '/' . $filename;

		file_put_contents($localPath, $data);

		$relativePath = 'books/' . $bookId . '/' . $filename;
		return ['original_path' => $relativePath, 'thumbnail_path' => $relativePath];
	}

	function storeImageFromPath(string $sourcePath, int $bookId, string $filenameBase): ?array
	{
		if (!file_exists($sourcePath)) {
			return null;
		}

		$bookDir = IMAGES_DIR . '/books/' . $bookId;
		if (!is_dir($bookDir)) {
			mkdir($bookDir, 0755, true);
		}

		$ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
		if (!$ext) {
			$ext = 'png';
		}

		$filename = $filenameBase . '-' . time() . '.' . $ext;
		$localPath = $bookDir . '/' . $filename;

		copy($sourcePath, $localPath);

		$relativePath = 'books/' . $bookId . '/' . $filename;
		return ['original_path' => $relativePath, 'thumbnail_path' => $relativePath];
	}

	function getStaticGroupedModels(): array
	{
		return [
			[
				'group' => 'Popular',
				'models' => [
					['id' => 'openai/gpt-5.1', 'name' => 'OpenAI GPT-5.1'],
					['id' => 'google/gemini-3-pro-preview', 'name' => 'Google: Gemini 3 Pro'],
					['id' => 'openai/gpt-4o', 'name' => 'OpenAI GPT-4o'],
					['id' => 'anthropic/claude-3.7-sonnet', 'name' => 'Claude 3.7 Sonnet'],
					['id' => 'anthropic/claude-3.7-sonnet:thinking', 'name' => 'Claude 3.7 Sonnet (Thinking)'],
					['id' => 'google/gemini-2.5-pro', 'name' => 'Google: Gemini 2.5 Pro'],
					['id' => 'deepseek/deepseek-chat-v3.1', 'name' => 'DeepSeek Chat V3.1'],
				],
			],
			[
				'group' => 'New',
				'models' => [
					['id' => 'anthropic/claude-sonnet-4', 'name' => 'Claude Sonnet 4'],
					['id' => 'openai/gpt-5', 'name' => 'OpenAI GPT-5'],
					['id' => 'openai/gpt-oss-120b', 'name' => 'OpenAI: gpt-oss-120b'],
					['id' => 'openai/gpt-5-chat', 'name' => 'OpenAI GPT-5 Chat'],
					['id' => 'openai/gpt-5-mini', 'name' => 'OpenAI GPT-5 mini'],
					['id' => 'moonshotai/kimi-k2-0905', 'name' => 'MoonshotAI: Kimi K2 0905'],
					['id' => 'z-ai/glm-4.5', 'name' => 'Z.AI: GLM 4.5'],
				],
			],
			[
				'group' => 'Other',
				'models' => [
					['id' => 'google/gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash'],
					['id' => 'openai/gpt-4.1', 'name' => 'OpenAI GPT-4.1'],
					['id' => 'openai/gpt-4o-mini', 'name' => 'OpenAI GPT-4o mini'],
				],
			],
			[
				'group' => 'NSFW',
				'models' => [
					['id' => 'qwen/qwen3-235b-a22b-2507', 'name' => 'Qwen 3 235b'],
					['id' => 'google/gemma-3-27b-it', 'name' => 'Gemma 3 27b'],
					['id' => 'mistralai/mistral-medium-3.1', 'name' => 'Mistral Medium 3.1'],
					['id' => 'mistralai/mistral-large-2411', 'name' => 'Mistral Large'],
					['id' => 'microsoft/wizardlm-2-8x22b', 'name' => 'WizardLM 2 8x22b'],
					['id' => 'x-ai/grok-4', 'name' => 'Grok 4'],
				],
			],
		];
	}
