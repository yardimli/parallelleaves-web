<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

require_once __DIR__ . '/ApiSupport.php';

class CodexApiController extends Controller
{
    public function books(Request $request): JsonResponse
    {
        try {
            $channel = 'codex:getAll';
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
                                        $stmt = $db->prepare('SELECT id, title, author, source_language, target_language, codex_status FROM user_books WHERE user_id = ? ORDER BY updated_at DESC');
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
            $channel = 'codex:getDetails';
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
                                        $stmt = $db->prepare('SELECT id, title, codex_content, codex_status FROM user_books WHERE id = ? AND user_id = ?');
                                        $stmt->execute([$bookId, $userId]);
                                        $result = $stmt->get_result()->fetch_assoc();
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function save(Request $request): JsonResponse
    {
        try {
            $channel = 'codex:save';
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
                                        $content = $args[1];
                                        $db->prepare('UPDATE user_books SET codex_content = ? WHERE id = ? AND user_id = ?')->execute([$content, $bookId, $userId]);
                                        $result = ['success' => true];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function reset(Request $request): JsonResponse
    {
        try {
            $channel = 'codex:reset';
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
                                        $db->prepare("UPDATE user_books SET codex_content = NULL, codex_status = 'none', codex_chunks_total = 0, codex_chunks_processed = 0 WHERE id = ? AND user_id = ?")->execute([$bookId, $userId]);
                
                                        // MODIFIED: Added deletion of chunks to ensure a fresh start on reset
                                        $db->prepare("DELETE FROM user_book_codex_chunks WHERE book_id = ?")->execute([$bookId]);
                
                                        $result = ['success' => true];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function start(Request $request): JsonResponse
    {
        try {
            $channel = 'codex:start';
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
                
                                        // MODIFIED: Check if chunks already exist to prevent overwriting progress
                                        $stmt = $db->prepare('SELECT COUNT(*) as total, SUM(is_processed) as processed FROM user_book_codex_chunks WHERE book_id = ?');
                                        $stmt->execute([$bookId]);
                                        $chunkStats = $stmt->get_result()->fetch_assoc();
                
                                        if ($chunkStats && $chunkStats['total'] > 0) {
                                            // Chunks exist, resume processing
                                            $totalChunks = (int)$chunkStats['total'];
                                            $processedChunks = (int)$chunkStats['processed'];
                
                                            if ($processedChunks >= $totalChunks) {
                                                $db->prepare("UPDATE user_books SET codex_status = 'complete', codex_chunks_total = ?, codex_chunks_processed = ? WHERE id = ?")->execute([$totalChunks, $processedChunks, $bookId]);
                                                $result = ['status' => 'complete'];
                                            } else {
                                                $db->prepare("UPDATE user_books SET codex_status = 'generating', codex_chunks_total = ?, codex_chunks_processed = ? WHERE id = ?")->execute([$totalChunks, $processedChunks, $bookId]);
                                                $result = ['status' => 'generating'];
                                            }
                                        } else {
                                            // No chunks exist, create them
                                            $stmt = $db->prepare('SELECT source_content FROM chapters WHERE book_id = ?');
                                            $stmt->execute([$bookId]);
                                            $chapters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                            $fullText = '';
                                            foreach ($chapters as $c) {
                                                $fullText .= htmlToPlainText($c['source_content'] ?? '') . "\n";
                                            }
                                            $words = preg_split('/\s+/', $fullText);
                                            $chunks = array_chunk($words, 8000);
                                            $totalChunks = count($chunks);
                
                                            if ($totalChunks > 0) {
                                                $stmt = $db->prepare('INSERT INTO user_book_codex_chunks (book_id, chunk_index, chunk_text, is_processed) VALUES (?, ?, ?, 0)');
                                                foreach ($chunks as $i => $chunk) {
                                                    $stmt->execute([$bookId, $i, implode(' ', $chunk)]);
                                                }
                
                                                $db->prepare("UPDATE user_books SET codex_status = 'generating', codex_chunks_total = ?, codex_chunks_processed = 0 WHERE id = ?")->execute([$totalChunks, $bookId]);
                                                $result = ['status' => 'generating'];
                                            } else {
                                                $db->prepare("UPDATE user_books SET codex_status = 'complete', codex_chunks_total = 0, codex_chunks_processed = 0 WHERE id = ?")->execute([$bookId]);
                                                $result = ['status' => 'complete'];
                                            }
                                        }
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function processBatch(Request $request): JsonResponse
    {
        try {
            $channel = 'codex:process-batch';
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
                                        $stmt = $db->prepare('SELECT * FROM user_books WHERE id = ?');
                                        $stmt->execute([$bookId]);
                                        $book = $stmt->get_result()->fetch_assoc();
                
                                        $chunkStmt = $db->prepare('SELECT * FROM user_book_codex_chunks WHERE book_id = ? AND is_processed = 0 ORDER BY chunk_index ASC LIMIT 1');
                                        $chunkStmt->execute([$bookId]);
                                        $chunk = $chunkStmt->get_result()->fetch_assoc();
                
                                        if (!$chunk) {
                                            $db->prepare("UPDATE user_books SET codex_status = 'complete' WHERE id = ?")->execute([$bookId]);
                                            $result = ['status' => 'complete'];
                                        } else {
                                            $systemPrompt = "You are a meticulous world-building assistant for a bookist. Your task is to maintain a codex (an encyclopedia of the world). Identify new characters, locations, or lore from the text chunk and integrate them. Your output must be the complete, updated codex in {$book['target_language']}.";
                                            $userPrompt = "**Existing Codex Content:**\n<codex>\n" . ($book['codex_content'] ?? 'This is the beginning of the codex.') . "\n</codex>\n\n**Text Chunk to Analyze (in {$book['source_language']}):**\n<text>\n{$chunk['chunk_text']}\n</text>";
                
                                            $payload = [
                                                'model' => OPEN_ROUTER_MODEL,
                                                'messages' => [
                                                    ['role' => 'system', 'content' => $systemPrompt],
                                                    ['role' => 'user', 'content' => $userPrompt]
                                                ],
                                                'temperature' => 0.5
                                            ];
                
                                            // MODIFIED: Passed $userApiKey
                                            $aiResponse = callOpenRouter($payload, ['db' => $db, 'userId' => $userId, 'action' => 'codex_llm_call'], $userApiKey);
                                            $updatedCodexText = trim($aiResponse['choices'][0]['message']['content'] ?? '');
                
                                            if ($updatedCodexText) {
                                                $db->prepare('UPDATE user_books SET codex_content = ?, codex_chunks_processed = codex_chunks_processed + 1 WHERE id = ?')->execute([$updatedCodexText, $bookId]);
                                            }
                                            $db->prepare('UPDATE user_book_codex_chunks SET is_processed = 1 WHERE id = ?')->execute([$chunk['id']]);
                                            $result = ['status' => 'generating', 'processed' => $book['codex_chunks_processed'] + 1, 'total' => $book['codex_chunks_total']];
                                        }
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
}
