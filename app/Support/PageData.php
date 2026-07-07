<?php

	namespace App\Support;

	require_once __DIR__ . '/../Http/Controllers/Api/ApiSupport.php';

	class PageData
	{
		public static function selectedLanguage(): string
		{
			$lang = request()->cookie('app_lang', 'en');
			return preg_match('/^[a-zA-Z0-9_-]+$/', $lang) ? $lang : 'en';
		}

		public static function translations(string $lang): array
		{
			return self::loadLanguage($lang);
		}

		public static function englishTranslations(): array
		{
			return self::loadLanguage('en');
		}

		public static function translate(array $translations, array $fallbackTranslations, string $key, ?string $fallback = null): string
		{
			$value = data_get($translations, $key, data_get($fallbackTranslations, $key, $fallback ?? $key));
			return is_string($value) ? $value : ($fallback ?? $key);
		}

		public static function translateHtml(string $html, array $translations, array $fallbackTranslations): string
		{
			$translate = fn(string $key, string $fallback = '') => e(self::translate($translations, $fallbackTranslations, $key, $fallback), false);
			$html = preg_replace_callback('/\{\{__i18n:([^|]+?)(?:\|([^}]*))?__\}\}/', function ($m) use ($translate) {
				return $translate($m[1], $m[2] ?? '');
			}, $html);
			$html = preg_replace_callback('/\sdata-i18n-placeholder="([^"]+)"/', fn($m) => ' placeholder="' . $translate($m[1]) . '"', $html);
			$html = preg_replace_callback('/\sdata-i18n-title="([^"]+)"/', fn($m) => ' title="' . $translate($m[1]) . '"', $html);
			$html = preg_replace_callback('/\sdata-i18n="([^"]*)">(.*?)</s', function ($m) use ($translate) {
				if ($m[1] === '') {
					return '>' . $m[2] . '<';
				}
				$fallback = trim(strip_tags($m[2]));
				return '>' . $translate($m[1], $fallback) . '<';
			}, $html);

			return $html;
		}

		public static function models(): array
		{
			$liveModelsData = [];
			$ch = curl_init('https://openrouter.ai/api/v1/models');
			if ($ch) {
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($ch, CURLOPT_TIMEOUT, 5);
				$response = curl_exec($ch);
				curl_close($ch);
				$liveModelsData = $response ? json_decode($response, true) : [];
			}

			return ['success' => true, 'models' => self::filterOpenRouterModels($liveModelsData['data'] ?? [])];
		}

		private static function filterOpenRouterModels(array $models): array
		{
			$rules = self::modelRules();
			$includeRules = array_map('strtolower', $rules['includeRules'] ?? []);
			$excludeRules = array_map('strtolower', $rules['excludeRules'] ?? []);
			$maxPrice = (float)($rules['maxPricePerMillion'] ?? 20.0);
			$descriptionOverrides = is_array($rules['descriptionOverrides'] ?? null) ? $rules['descriptionOverrides'] : [];
			$grouped = [];

			foreach ($models as $model) {
				if (!is_array($model) || empty($model['id'])) {
					continue;
				}

				$id = (string)$model['id'];
				$name = (string)($model['name'] ?? $id);
				$haystack = strtolower($id . ' ' . $name);

				if (!self::matchesAnyRule($haystack, $includeRules)) {
					continue;
				}

				if (self::matchesAnyRule($haystack, $excludeRules)) {
					continue;
				}

				$price = self::maxModelPricePerMillion($model);
				if ($price !== null && $price > $maxPrice) {
					continue;
				}

				$provider = self::modelProvider($id);
				$grouped[$provider][] = [
					'id' => $id,
					'name' => $name,
					'description' => $descriptionOverrides[$id] ?? ($model['description'] ?? ''),
					'prompt_price_per_million' => self::modelPricePerMillion($model, 'prompt'),
					'completion_price_per_million' => self::modelPricePerMillion($model, 'completion'),
				];
			}

			$result = [];
			foreach ($grouped as $provider => $providerModels) {
				usort($providerModels, fn(array $a, array $b) => strcasecmp($a['name'], $b['name']));
				$result[] = [
					'group' => $provider,
					'models' => $providerModels,
				];
			}

			usort($result, fn(array $a, array $b) => strcasecmp($a['group'], $b['group']));

			return $result;
		}

		private static function modelRules(): array
		{
			$path = config_path('openrouter-model-rules.json');
			if (!is_file($path)) {
				return [];
			}

			$contents = file_get_contents($path);
			if (!$contents) {
				return [];
			}

			try {
				$rules = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
			} catch (\JsonException) {
				return [];
			}

			return is_array($rules) ? $rules : [];
		}

		private static function matchesAnyRule(string $haystack, array $rules): bool
		{
			foreach ($rules as $rule) {
				if ($rule !== '' && str_contains($haystack, $rule)) {
					return true;
				}
			}

			return false;
		}

		private static function modelProvider(string $modelId): string
		{
			$provider = strtok($modelId, '/') ?: 'Other';

			return match ($provider) {
				'openai' => 'OpenAI',
				'anthropic' => 'Anthropic',
				'google' => 'Google',
				'perplexity' => 'Perplexity',
				'meta-llama' => 'Meta Llama',
				default => ucwords(str_replace(['-', '_'], ' ', $provider)),
			};
		}

		private static function modelPricePerMillion(array $model, string $key): ?float
		{
			$value = $model['pricing'][$key] ?? null;
			if ($value === null || !is_numeric($value)) {
				return null;
			}

			return (float)$value * 1000000;
		}

		private static function maxModelPricePerMillion(array $model): ?float
		{
			$prices = array_filter([
				self::modelPricePerMillion($model, 'prompt'),
				self::modelPricePerMillion($model, 'completion'),
			], fn(?float $price) => $price !== null);

			return empty($prices) ? null : max($prices);
		}

		public static function viewData(array $extra = []): array
		{
			$lang = self::selectedLanguage();
			$translations = self::translations($lang);
			$englishTranslations = self::englishTranslations();

			return array_merge([
				'selectedLang' => $lang,
				'translations' => $translations,
				'englishTranslations' => $englishTranslations,
				'modelsData' => self::models(),
				'tr' => fn(string $key, ?string $fallback = null) => self::translate($translations, $englishTranslations, $key, $fallback),
			], $extra);
		}

		private static function loadLanguage(string $lang): array
		{
			$dir = base_path('lang/' . $lang);
			$merged = [];
			if (!is_dir($dir)) {
				return $merged;
			}

			foreach (glob($dir . '/*.json') as $file) {
				$key = basename($file, '.json');
				$fileContent = file_get_contents($file);
				$merged[$key] = $fileContent ? json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR) : [];
			}

			return $merged;
		}
	}
