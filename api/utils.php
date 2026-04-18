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

			// Strip out all color and background-color CSS properties from inline styles
			$html = preg_replace('/(color|background-color)\s*:\s*[^;"\']+;?/i', '', $html);

			// Strip out legacy color attributes (e.g., <font color="red"> or <td bgcolor="black">)
			$html = preg_replace('/\s(color|bgcolor)="[^"]*"/i', '', $html);

			// Clean up any empty style or class attributes left behind
			$html = preg_replace('/style="\s*"/i', '', $html);
			$html = preg_replace('/class="\s*"/i', '', $html);

			// Remove soft hyphens (&shy;) which Word often inserts and breaks words in the editor
			$html = str_replace(['&shy;', "\xC2\xAD"], '', $html);

			// Safely unwrap useless <span> tags using DOMDocument
			if (trim($html) !== '') {
				$dom = new DOMDocument();
				// Suppress warnings for malformed HTML
				libxml_use_internal_errors(true);

				// Wrap in a div and specify UTF-8 to prevent encoding issues
				$dom->loadHTML(
					'<?xml encoding="utf-8" ?><div>' . $html . '</div>',
					LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
				);
				libxml_clear_errors();

				$spans = $dom->getElementsByTagName('span');

				// Iterate backwards because we are modifying the live NodeList
				for ($i = $spans->length - 1; $i >= 0; $i--) {
					$span = $spans->item($i);

					// If the span has no attributes left, unwrap it (replace it with its children)
					if ($span->attributes->length === 0) {
						$fragment = $dom->createDocumentFragment();
						while ($span->childNodes->length > 0) {
							$fragment->appendChild($span->childNodes->item(0));
						}
						$span->parentNode->replaceChild($fragment, $span);
					}
				}

				// Extract the inner HTML of our wrapper <div>
				$html = '';
				foreach ($dom->documentElement->childNodes as $child) {
					$html .= $dom->saveHTML($child);
				}
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

// MODIFIED: Added $apiKey parameter to use the user's specific OpenRouter API Key
	function callOpenRouter(array $payload, ?array $logContext = null, string $apiKey = ''): array
	{
		if (empty($apiKey)) {
			throw new Exception('OpenRouter API key is missing. Please set it in your account settings.');
		}

		$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $apiKey,
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
					['id' => 'anthropic/claude-sonnet-4.6', 'name' => 'Claude Sonnet 4.6'],
					['id' => 'anthropic/claude-opus-4.6', 'name' => 'Claude Opus 4.6'],
					['id' => 'deepseek/deepseek-v3.2', 'name' => 'DeepSeek V3.2'],
					['id' => 'minimax/minimax-m2.7', 'name' => 'Minimax M2.7'],
					['id' => 'google/gemini-3.1-pro-preview', 'name' => 'Google: Gemini 3.1 Pro Preview'],
					['id' => 'openai/gpt-5.4', 'name' => 'OpenAI GPT-5.4'],
					['id' => 'anthropic/claude-3.7-sonnet', 'name' => 'Claude 3.7 Sonnet'],
					['id' => 'anthropic/claude-3.7-sonnet:thinking', 'name' => 'Claude 3.7 Sonnet (Thinking)'],
					['id' => 'google/gemini-2.5-pro', 'name' => 'Google: Gemini 2.5 Pro'],
				],
			],
			[
				'group' => 'New',
				'models' => [
					['id' => 'anthropic/claude-sonnet-4', 'name' => 'Claude Sonnet 4'],
					['id' => 'google/gemma-4-26b-a4b-it', 'name' => 'Google: Gemma 4 26b IT'],
					['id' => 'qwen/qwen3.6-plus', 'name' => 'Qwen 3.6 Plus'],
					['id' => 'openai/gpt-5.4-mini', 'name' => 'OpenAI GPT-5.4 mini'],
					['id' => 'openai/gpt-5', 'name' => 'OpenAI GPT-5'],
					['id' => 'openai/gpt-oss-120b', 'name' => 'OpenAI: gpt-oss-120b'],
					['id' => 'moonshotai/kimi-k2.5', 'name' => 'MoonshotAI: Kimi K2.5'],
					['id' => 'z-ai/glm-4.5', 'name' => 'Z.AI: GLM 4.5'],
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
