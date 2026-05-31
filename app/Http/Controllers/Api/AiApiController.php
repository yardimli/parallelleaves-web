<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use App\Support\PageData;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class AiApiController extends Controller
	{
		public function models(Request $request): JsonResponse
		{
			try {
				$channel = 'ai:getModels';
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$user = Auth::user();

				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$result = null;
				do {
					session_write_close();
					$result = PageData::models();
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function coverPrompt(Request $request): JsonResponse
		{
			try {
				$channel = 'ai:generate-cover-prompt';
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$user = Auth::user();

				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$result = null;
				do {
					$bookTitle = $args[0]['bookTitle'] ?? '';
					$prompt = "Using the book title \"$bookTitle\", write a clear and simple description of a scene for an AI image generator to create a book cover. Include the setting, mood, and main objects. Include the \"$bookTitle\" in the prompt Return the result as a JSON with one key \"prompt\". Example: with title \"Blue Scape\" {\"prompt\": \"An astronaut on a red planet looking at a big cosmic cloud, realistic, add the title \\\"Blue Scape\\\" to the image.\"}";
					$payload = [
						'model' => env('OPEN_ROUTER_MODEL', 'openai/gpt-4o-mini'),
						'messages' => [['role' => 'user', 'content' => $prompt]],
						'response_format' => ['type' => 'json_object'],
						'temperature' => 0.7
					];

					// MODIFIED: Passed sanitized context array without raw $db
					$res = callOpenRouter(
						$payload,
						['userId' => $userId, 'action' => 'generate_cover_prompt'],
						$userApiKey
					);
					$content = json_decode($res['choices'][0]['message']['content'] ?? '{}', true);
					$result = ['success' => true, 'prompt' => $content['prompt'] ?? null];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function generateCover(Request $request): JsonResponse
		{
			try {
				$channel = 'ai:generate-cover';
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$user = Auth::user();

				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$result = null;
				do {
					$bookId = $args[0]['bookId'];
					$prompt = $args[0]['prompt'];
					$falPayload = ['prompt' => $prompt, 'image_size' => 'portrait_4_3'];
					session_write_close();
					$ch = curl_init('https://fal.run/fal-ai/qwen-image-2/text-to-image');
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($falPayload));
					curl_setopt($ch, CURLOPT_HTTPHEADER, [
						'Authorization: Key ' . env('FAL_API_KEY', ''),
						'Content-Type: application/json',
						'Accept: application/json'
					]);

					// MODIFIED: Bypassed SSL verification to prevent handshaking failures on local development servers [1]
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

					$response = curl_exec($ch);

					if (!$response) {
						// MODIFIED: Added detailed cURL error messages to aid in connection troubleshooting [1]
						$curlError = curl_error($ch);
						throw new Exception('Image generation API call failed. cURL Error: ' . $curlError);
					}

					$falData = json_decode($response, true);
					if (!isset($falData['images'][0]['url'])) {
						// MODIFIED: Read structural error responses from fal.ai (such as missing balance or validation errors) and append them to the exception [1]
						$errorMessage = 'Image generation failed.';
						if (isset($falData['detail'])) {
							if (is_array($falData['detail'])) {
								$msgs = [];
								foreach ($falData['detail'] as $err) {
									if (isset($err['msg'])) {
										$msgs[] = $err['msg'];
									}
								}
								if (!empty($msgs)) {
									$errorMessage .= ' Reason: ' . implode(', ', $msgs);
								} else {
									$errorMessage .= ' Reason: ' . json_encode($falData['detail']);
								}
							} else {
								$errorMessage .= ' Reason: ' . $falData['detail'];
							}
						} elseif (isset($falData['error'])) {
							$errorMessage .= ' Reason: ' . $falData['error'];
						} else {
							$errorMessage .= ' Response: ' . $response;
						}
						throw new Exception($errorMessage);
					}

					$localPaths = storeImageFromUrl($falData['images'][0]['url'], $bookId, 'generated-fal');
					if (!$localPaths) {
						throw new Exception('Failed to save generated cover.');
					}

					$fullPath = '/storage/userData/images/' . $localPaths['original_path'];
					$result = ['success' => true, 'filePath' => $fullPath, 'localPath' => $localPaths['original_path']];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}
