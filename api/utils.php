<?php

	declare(strict_types=1);

	function htmlToPlainText(string $html): string
	{
		if (!$html) return '';
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
		if (!$html) return 0;
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

	function callOpenRouter(array $payload): array
	{
		$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . OPEN_ROUTER_API_KEY,
			'HTTP-Referer: https://paralleleaves.com',
			'X-Title: Parallel Leaves',
			'Content-Type: application/json'
		]);
		$response = curl_exec($ch);
		curl_close($ch);
		return json_decode($response, true) ?? [];
	}
