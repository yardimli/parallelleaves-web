<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $db = getDB();
            $userId = $user?->id;
            $userApiKey = $user?->openrouter_api_key ?? '';

            if ($user) {
                $_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
            }

            $result = null;
            do {
                                        session_write_close();
                                        $ch = curl_init('https://openrouter.ai/api/v1/models');
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                                        $response = curl_exec($ch);
                
                                        $liveModelsData = $response ? json_decode($response, true) : [];
                
                                        $availableModelIds = array_flip(array_column($liveModelsData['data'] ?? [], 'id'));
                                        $staticGroupedModels = getStaticGroupedModels();
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
                                        $result = ['success' => true, 'models' => $verifiedGroupedModels];
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
            $db = getDB();
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
                                            'model' => OPEN_ROUTER_MODEL,
                                            'messages' => [['role' => 'user', 'content' => $prompt]],
                                            'response_format' => ['type' => 'json_object'],
                                            'temperature' => 0.7
                                        ];
                                        // MODIFIED: Passed $userApiKey
                                        $res = callOpenRouter($payload, ['db' => $db, 'userId' => $userId, 'action' => 'generate_cover_prompt'], $userApiKey);
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
            $db = getDB();
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
                
                                        if (!$response) {
                                            throw new Exception('Image generation API call failed.');
                                        }
                                        $falData = json_decode($response, true);
                                        if (!isset($falData['images'][0]['url'])) {
                                            throw new Exception('Image generation failed.');
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
