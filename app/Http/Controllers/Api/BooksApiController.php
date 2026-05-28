<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

require_once __DIR__ . '/ApiSupport.php';

class BooksApiController extends Controller
{
    public function withCovers(Request $request): JsonResponse
    {
        try {
            $channel = 'books:getAllWithCovers';
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
                                        $stmt = $db->prepare("
                                        SELECT n.*, i.image_local_path as cover_path,
                                        (SELECT COUNT(id) FROM chapters WHERE book_id = n.id) as chapter_count
                                        FROM user_books n
                                        LEFT JOIN images i ON n.id = i.book_id
                                        WHERE n.user_id = ? ORDER BY n.updated_at DESC
                                    ");
                                        $stmt->execute([$userId]);
                                        $books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                        foreach ($books as &$book) {
                                            $chStmt = $db->prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ?');
                                            $chStmt->execute([$book['id']]);
                                            $chapters = $chStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                            $book['source_word_count'] = array_sum(array_map(fn($c) => countWordsInHtml($c['source_content'] ?? ''), $chapters));
                                            $book['target_word_count'] = array_sum(array_map(fn($c) => countWordsInHtml($c['target_content'] ?? ''), $chapters));
                                            if ($book['cover_path']) {
                                                $book['cover_path'] = '/storage/userData/images/' . $book['cover_path'];
                                            }
                                        }
                                        $result = $books;
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function withTranslationMemory(Request $request): JsonResponse
    {
        try {
            $channel = 'books:getAllWithTranslationMemory';
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
                                        $stmt = $db->prepare('SELECT DISTINCT b.id, b.title FROM user_books_translation_memory tm JOIN user_books b ON tm.book_id = b.id WHERE b.user_id = ? ORDER BY b.title ASC');
                                        $stmt->execute([$userId]);
                                        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function get(Request $request): JsonResponse
    {
        try {
            $channel = 'books:getOne';
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
                                        $stmt = $db->prepare('SELECT * FROM user_books WHERE id = ? AND user_id = ?');
                                        $stmt->execute([$bookId, $userId]);
                                        $book = $stmt->get_result()->fetch_assoc();
                                        if ($book) {
                                            $chStmt = $db->prepare('SELECT * FROM chapters WHERE book_id = ? ORDER BY chapter_order');
                                            $chStmt->execute([$bookId]);
                                            $book['chapters'] = $chStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                            foreach ($book['chapters'] as &$chapter) {
                                                $chapter['source_word_count'] = countWordsInHtml($chapter['source_content'] ?? '');
                                                $chapter['target_word_count'] = countWordsInHtml($chapter['target_content'] ?? '');
                                            }
                                        }
                                        $result = $book;
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function fullManuscript(Request $request): JsonResponse
    {
        try {
            $channel = 'books:getFullManuscript';
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
                                        $stmt = $db->prepare('SELECT * FROM user_books WHERE id = ? AND user_id = ?');
                                        $stmt->execute([$bookId, $userId]);
                                        $book = $stmt->get_result()->fetch_assoc();
                                        if ($book) {
                                            $chStmt = $db->prepare('SELECT * FROM chapters WHERE book_id = ? ORDER BY chapter_order');
                                            $chStmt->execute([$bookId]);
                                            $book['chapters'] = $chStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                            foreach ($book['chapters'] as &$chapter) {
                                                $chapter['source_word_count'] = countWordsInHtml($chapter['source_content'] ?? '');
                                                $chapter['target_word_count'] = countWordsInHtml($chapter['target_content'] ?? '');
                                            }
                                        }
                                        $result = $book;
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function allContent(Request $request): JsonResponse
    {
        try {
            $channel = 'books:getAllBookContent';
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
                                        $stmt = $db->prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ?');
                                        $stmt->execute([$bookId]);
                                        $chapters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                        $combined = '';
                                        foreach ($chapters as $c) {
                                            $combined .= ($c['source_content'] ?? '') . ($c['target_content'] ?? '');
                                        }
                                        $result = ['success' => true, 'combinedHtml' => $combined];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function forExport(Request $request): JsonResponse
    {
        try {
            $channel = 'books:getForExport';
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
                                        $stmt = $db->prepare('SELECT id, title, author, target_language FROM user_books WHERE id = ? AND user_id = ?');
                                        $stmt->execute([$bookId, $userId]);
                                        $book = $stmt->get_result()->fetch_assoc();
                                        if (!$book) {
                                            throw new Exception('Book not found.');
                                        }
                                        $chStmt = $db->prepare('SELECT id, title, target_content FROM chapters WHERE book_id = ? ORDER BY chapter_order');
                                        $chStmt->execute([$bookId]);
                                        $book['chapters'] = $chStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                        $result = ['success' => true, 'data' => $book];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function createBlank(Request $request): JsonResponse
    {
        try {
            $channel = 'books:createBlank';
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
                                        $chStmt = $db->prepare('INSERT INTO chapters (book_id, title, chapter_order, source_content, target_content) VALUES (?, ?, ?, ?, ?)');
                                        for ($i = 1; $i <= 10; $i++) {
                                            $chStmt->execute([$bookId, "Chapter $i", $i, '<p></p>', '<p></p>']);
                                        }
                                        $result = ['success' => true, 'bookId' => $bookId];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function updateMeta(Request $request): JsonResponse
    {
        try {
            $channel = 'books:updateMeta';
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
                                        $stmt = $db->prepare('UPDATE user_books SET title = ?, author = ? WHERE id = ? AND user_id = ?');
                                        $stmt->execute([$data['title'], $data['author'], $data['bookId'], $userId]);
                                        $result = ['success' => true];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function updateProseSettings(Request $request): JsonResponse
    {
        try {
            $channel = 'books:updateProseSettings';
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
                                        $stmt = $db->prepare('UPDATE user_books SET source_language = ?, target_language = ? WHERE id = ? AND user_id = ?');
                                        $stmt->execute([$data['source_language'], $data['target_language'], $data['bookId'], $userId]);
                                        $result = ['success' => true];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function updatePromptSettings(Request $request): JsonResponse
    {
        try {
            $channel = 'books:updatePromptSettings';
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
                                        $allowedTypes = ['rephrase', 'translate'];
                                        if (!in_array($data['promptType'], $allowedTypes)) {
                                            throw new Exception('Invalid prompt type.');
                                        }
                                        $field = $data['promptType'] . '_settings';
                                        $stmt = $db->prepare("UPDATE user_books SET $field = ? WHERE id = ? AND user_id = ?");
                                        $stmt->execute([json_encode($data['settings']), $data['bookId'], $userId]);
                                        $result = ['success' => true];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function updateCover(Request $request): JsonResponse
    {
        try {
            $channel = 'books:updateBookCover';
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
                                        $bookId = $data['bookId'];
                                        $coverInfo = $data['coverInfo'];
                                        $localPath = null;
                                        $imageType = 'unknown';
                
                                        if ($coverInfo['type'] === 'remote') {
                                            $paths = storeImageFromUrl($coverInfo['data'], $bookId, 'cover');
                                            $localPath = $paths['original_path'] ?? null;
                                            $imageType = 'generated';
                                        } elseif ($coverInfo['type'] === 'local') {
                                            $paths = storeImageFromPath($coverInfo['data'], $bookId, 'cover-upload');
                                            $localPath = $paths['original_path'] ?? null;
                                            $imageType = 'upload';
                                        } elseif ($coverInfo['type'] === 'existing') {
                                            // MODIFIED: Handle existing local path (e.g. from ai:generate-cover)
                                            $localPath = $coverInfo['data'];
                                            $imageType = 'generated';
                                        }
                
                                        if (!$localPath) {
                                            throw new Exception('Failed to store the new cover image.');
                                        }
                
                                        $oldImage = $db->prepare('SELECT image_local_path FROM images WHERE book_id = ?');
                                        $oldImage->execute([$bookId]);
                                        $old = $oldImage->get_result()->fetch_assoc();
                                        // MODIFIED: Only delete old image if it's different from the new one
                                        if ($old && $old['image_local_path'] && $old['image_local_path'] !== $localPath) {
                                            @unlink(IMAGES_DIR . '/' . $old['image_local_path']);
                                        }
                
                                        $db->prepare('DELETE FROM images WHERE book_id = ?')->execute([$bookId]);
                                        $db->prepare('INSERT INTO images (user_id, book_id, image_local_path, thumbnail_local_path, image_type) VALUES (?, ?, ?, ?, ?)')
                                            ->execute([$userId, $bookId, $localPath, $localPath, $imageType]);
                
                                        $result = ['success' => true, 'imagePath' => '/storage/userData/images/' . $localPath];
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
            $channel = 'books:delete';
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
                                        $images = $db->prepare('SELECT image_local_path FROM images WHERE book_id = ?');
                                        $images->execute([$bookId]);
                                        foreach ($images->get_result()->fetch_all(MYSQLI_ASSOC) as $img) {
                                            @unlink(IMAGES_DIR . '/' . $img['image_local_path']);
                                        }
                                        $db->prepare('DELETE FROM images WHERE book_id = ?')->execute([$bookId]);
                                        $db->prepare('DELETE FROM user_books WHERE id = ? AND user_id = ?')->execute([$bookId, $userId]);
                                        $result = ['success' => true];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function export(Request $request): JsonResponse
    {
        try {
            $channel = 'books:exportToDocx';
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
                                        $filename = preg_replace('/[^a-z0-9]/i', '_', $data['title']) . '_' . time() . '.doc';
                                        $filePath = DOWNLOADS_DIR . '/' . $filename;
                                        $html = "<html><head><meta charset='utf-8'></head><body>" . $data['htmlContent'] . "</body></html>";
                                        file_put_contents($filePath, $html);
                                        $result = ['success' => true, 'downloadUrl' => '/storage/userData/downloads/' . $filename, 'filename' => $filename];
                                        break;
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function highestMarker(Request $request): JsonResponse
    {
        try {
            $channel = 'books:findHighestMarkerNumber';
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
                                        $result = findHighestMarkerNumber($args[0], $args[1]);
                                        break;
                
                                    // --- Chapters ---
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
}
