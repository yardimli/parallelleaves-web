<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use App\Models\UserBook; // MODIFIED: Imported Eloquent Models
	use App\Models\Chapter;
	use App\Models\Image;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class DocumentsApiController extends Controller
	{
		public function read(Request $request): JsonResponse
		{
			try {
				$channel = 'document:read';
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
					$filePath = $args[0];
					$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
					$result = $ext === 'docx' ? readDocx($filePath) : readTextFileAsUtf8($filePath);
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		private static function generateImportCover(int $bookId, int $userId, string $userApiKey, string $title): void
		{
			try {
				$promptPayload = [
					'model' => env('OPEN_ROUTER_MODEL', 'openai/gpt-4o-mini'),
					'messages' => [['role' => 'user', 'content' => "Using the book title \"{$title}\", write a clear and simple description of a scene for an AI image generator to create a book cover. Include the setting, mood, and main objects. Include the \"{$title}\" in the prompt Return the result as a JSON with one key \"prompt\"."]],
					'response_format' => ['type' => 'json_object'],
					'temperature' => 0.7
				];

				$res = callOpenRouter(
					$promptPayload,
					['userId' => $userId, 'action' => 'generate_cover_prompt'],
					$userApiKey
				);
				$content = json_decode($res['choices'][0]['message']['content'] ?? '{}', true);
				$prompt = $content['prompt'] ?? null;

				if (!$prompt) {
					return;
				}

				$falPayload = ['prompt' => $prompt, 'image_size' => 'portrait_4_3'];
				$ch = curl_init('https://fal.run/fal-ai/qwen-image-2/text-to-image');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($falPayload));
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Authorization: Key ' . env('FAL_API_KEY', ''),
					'Content-Type: application/json',
					'Accept: application/json'
				]);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

				$response = curl_exec($ch);
				curl_close($ch);
				$falData = $response ? json_decode($response, true) : [];

				if (!isset($falData['images'][0]['url'])) {
					return;
				}

				$localPaths = storeImageFromUrl($falData['images'][0]['url'], $bookId, 'cover-autogen');
				if ($localPaths) {
					Image::create([
						'user_id' => $userId,
						'book_id' => $bookId,
						'image_local_path' => $localPaths['original_path'],
						'thumbnail_local_path' => $localPaths['original_path'],
						'image_type' => 'generated',
						'prompt' => $prompt
					]);
				}
			} catch (Throwable $exception) {
				error_log('Auto-cover generation failed: ' . $exception->getMessage());
			}
		}

		public function import(Request $request): JsonResponse
		{
			try {
				$channel = 'document:import';
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
					$data = $args[0];
					// MODIFIED: Replaced raw INSERT preparation with Eloquent model insertion [1]
					$book = UserBook::create([
						'user_id' => $userId,
						'title' => $data['title'],
						'source_language' => $data['source_language'],
						'target_language' => $data['target_language']
					]);
					$bookId = $book->id;

					foreach ($data['chapters'] as $i => $chapter) {
						Chapter::create([
							'book_id' => $bookId,
							'title' => $chapter['title'],
							'source_content' => $chapter['content'],
							'chapter_order' => $i + 1
						]);
					}

					$title = (string)$data['title'];
					dispatch(function () use ($bookId, $userId, $userApiKey, $title) {
						self::generateImportCover((int)$bookId, (int)$userId, (string)$userApiKey, $title);
					})->afterResponse();

					$result = ['success' => true, 'bookId' => $bookId];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}
