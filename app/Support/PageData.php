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

			$staticGroupedModels = function_exists('App\Http\Controllers\Api\getStaticGroupedModels')
				? \App\Http\Controllers\Api\getStaticGroupedModels()
				: [];

			$availableModelIds = array_flip(array_column($liveModelsData['data'] ?? [], 'id'));
			if (empty($availableModelIds)) {
				return ['success' => true, 'models' => $staticGroupedModels];
			}

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

			return ['success' => true, 'models' => $verifiedGroupedModels];
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
