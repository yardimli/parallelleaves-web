<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
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
				$db = getDB();
				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$result = null;
				do {
					$filePath = $args[0];
					$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
					$result = $ext === 'docx' ? readDocx($filePath) : file_get_contents($filePath);
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function import(Request $request): JsonResponse
		{
			try {
				$channel = 'document:import';
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$user = Auth::user();
				$db = getDB();
				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$result = null;
				do {
					$data = $args[0];
					$stmt = $db->prepare('INSERT into user_books (user_id, title, source_language, target_language) VALUES (?, ?, ?, ?)');
					$stmt->execute([$userId, $data['title'], $data['source_language'], $data['target_language']]);
					$bookId = $db->insert_id;
					$chStmt = $db->prepare('INSERT INTO chapters (book_id, title, source_content, chapter_order) VALUES (?, ?, ?, ?)');
					foreach ($data['chapters'] as $i => $chapter) {
						$chStmt->execute([$bookId, $chapter['title'], $chapter['content'], $i + 1]);
					}

					// Generate cover automatically on import
					try {
						$promptPayload = [
							'model' => OPEN_ROUTER_MODEL,
							'messages' => [['role' => 'user', 'content' => "Using the book title \"{$data['title']}\", write a clear and simple description of a scene for an AI image generator to create a book cover. Include the setting, mood, and main objects. Include the \"{$data['title']}\" in the prompt Return the result as a JSON with one key \"prompt\"."]],
							'response_format' => ['type' => 'json_object'],
							'temperature' => 0.7
						];
						// MODIFIED: Passed $userApiKey
						$res = callOpenRouter($promptPayload, ['db' => $db, 'userId' => $userId, 'action' => 'generate_cover_prompt'], $userApiKey);
						$content = json_decode($res['choices'][0]['message']['content'] ?? '{}', true);
						$prompt = $content['prompt'] ?? null;

						if ($prompt) {
							$falPayload = ['prompt' => $prompt, 'image_size' => 'portrait_4_3'];
							session_write_close();
							$ch = curl_init('https://fal.run/fal-ai/qwen-image');
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
							curl_setopt($ch, CURLOPT_POST, true);
							curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($falPayload));
							curl_setopt($ch, CURLOPT_HTTPHEADER, [
								'Authorization: Key ' . FAL_API_KEY,
								'Content-Type: application/json',
								'Accept: application/json'
							]);
							$response = curl_exec($ch);

							$falData = $response ? json_decode($response, true) : [];

							if (isset($falData['images'][0]['url'])) {
								$localPaths = storeImageFromUrl($falData['images'][0]['url'], $bookId, 'cover-autogen');
								if ($localPaths) {
									$db->prepare('INSERT INTO images (user_id, book_id, image_local_path, thumbnail_local_path, image_type, prompt) VALUES (?, ?, ?, ?, ?, ?)')
										->execute([$userId, $bookId, $localPaths['original_path'], $localPaths['original_path'], 'generated', $prompt]);
								}
							}
						}
					} catch (Exception $e) {
						// Silently fail cover generation to not break import
						error_log("Auto-cover generation failed: " . $e->getMessage());
					}

					$result = ['success' => true, 'bookId' => $bookId];
					break;

					// --- AI & LLM ---
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}
