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
		$zip = new ZipArchive();
		if ($zip->open($filePath) === true) {
			if (($index = $zip->locateName('word/document.xml')) !== false) {
				$data = $zip->getFromIndex($index);
				$zip->close();
				return strip_tags(str_replace(['<w:p>', '<w:p '], "\n\n<w:p>", $data));
			}
			$zip->close();
		}
		return "";
	}

// NEW: Log API interactions to the database
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

// MODIFIED: Added optional $logContext for database logging
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
		//curl_close($ch);

		if ($logContext && isset($logContext['db'], $logContext['userId'], $logContext['action'])) {
			logInteraction($logContext['db'], $logContext['userId'], $logContext['action'], $payload, (string)$response, $httpCode);
		}

		return json_decode($response, true) ?? [];
	}

// NEW: Extract translation pairs from HTML for Translation Memory
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
