<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

require_once __DIR__ . '/ApiSupport.php';

class UserApiController extends Controller
{
    public function setApiKey(Request $request): JsonResponse
    {
        try {
            $channel = 'user:set-api-key';
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
                                        // MODIFIED: Added endpoint to update the user's API key
                                        $newKey = $args[0] ?? '';
                                        $stmt = $db->prepare('UPDATE users SET openrouter_api_key = ? WHERE id = ?');
                                        $stmt->execute([$newKey, $userId]);
                                        $_SESSION['user']['openrouter_api_key'] = $newKey;
                                        $result = ['success' => true];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
}
