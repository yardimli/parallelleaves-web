<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

require_once __DIR__ . '/ApiSupport.php';

class TmApiController extends Controller
{
    public function books(Request $request): JsonResponse
    {
        try {
            $channel = 'tm:getAll';
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
                                        $stmt = $db->prepare('SELECT n.id, n.title, n.author, n.source_language, n.target_language, (SELECT COUNT(*) from user_books_translation_memory WHERE book_id = n.id) as tm_count FROM user_books n WHERE n.user_id = ? ORDER BY n.updated_at DESC');
                                        $stmt->execute([$userId]);
                                        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function details(Request $request): JsonResponse
    {
        try {
            $channel = 'tm:getDetails';
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
                                        $bookId = $args[0];
                                        $stmt = $db->prepare('SELECT source_sentence, target_sentence from user_books_translation_memory WHERE book_id = ? ORDER BY id ASC');
                                        $stmt->execute([$bookId]);
                                        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        try {
            $channel = 'tm:delete';
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
                                        $bookId = $args[0];
                                        $db->prepare('DELETE from user_books_translation_memory WHERE book_id = ?')->execute([$bookId]);
                                        $db->prepare('UPDATE user_book_blocks SET is_analyzed = 0 WHERE book_id = ?')->execute([$bookId]);
                                        $result = ['success' => true];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
}
