<?php

namespace App\Http\Controllers;

use App\Models\UserBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

class BookArchiveController extends Controller
{
    public function download(Request $request, int $bookId)
    {
        $book = UserBook::where('user_id', $request->user()->id)->findOrFail($bookId);
        $filename = (Str::slug($book->title) ?: 'manuscript') . '-complete.zip';

        return response()->streamDownload(function () use ($book) {
            $path = tempnam(sys_get_temp_dir(), 'pl-book-');
            $zip = new ZipArchive();
            $opened = false;
            try {
                if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                    throw new \RuntimeException('Unable to create book archive.');
                }
                $opened = true;
                $json = fn ($value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $zip->addFromString('book.json', $json($book->makeHidden(['user_id'])->toArray()));
                $zip->addFromString('chapters.json', $json($book->chapters()->get()->toArray()));
                foreach ([
                    'dictionary' => 'user_book_dictionaries',
                    'translation-memory' => 'user_books_translation_memory',
                    'translation-memory-blocks' => 'translation_memory_blocks',
                    'book-blocks' => 'user_book_blocks',
                    'codex-chunks' => 'user_book_codex_chunks',
                    'translation-history' => 'translation_logs',
                ] as $name => $table) {
                    $zip->addFromString($name . '.json', $json(DB::table($table)->where('book_id', $book->id)->orderBy('id')->get()));
                }

                $root = realpath(storage_path('app/public/userData/images'));
                $covers = [];
                foreach ($book->images()->where('user_id', $book->user_id)->get() as $image) {
                    $entry = $image->makeHidden(['user_id'])->toArray();
                    $entry['archive_files'] = [];
                    foreach (['image_local_path', 'thumbnail_local_path'] as $field) {
                        if (!$root || !$image->$field) continue;
                        $source = realpath($root . DIRECTORY_SEPARATOR . $image->$field);
                        // Archive only local files inside the image directory. Never fetch remote URLs.
                        $insideRoot = $source && (DIRECTORY_SEPARATOR === '\\'
                            ? str_starts_with(strtolower($source), strtolower($root . DIRECTORY_SEPARATOR))
                            : str_starts_with($source, $root . DIRECTORY_SEPARATOR));
                        if ($insideRoot && is_file($source)) {
                            $destination = 'covers/' . $image->id . '-' . $field . '-' . basename($source);
                            $zip->addFile($source, $destination);
                            $entry['archive_files'][] = $destination;
                        }
                    }
                    $covers[] = $entry;
                }
                $zip->addFromString('covers.json', $json($covers));
                $zip->addFromString('README.txt', "Parallel Leaves book archive v1\nExported: " . now()->toIso8601String() . "\n\nbook.json: metadata, Codex, style analysis, and prompt settings.\nchapters.json: ordered source and target content, stored as HTML.\nOther JSON files: dictionary, all memory rows, alignment blocks, Codex chunks, and translation history.\ncovers.json lists cover metadata and included local files; missing or remote-only files are not downloaded.\n\nAccount credentials and API keys are not included. This is a portable data export, not an automatically importable backup.\n");
                if (!$zip->close()) throw new \RuntimeException('Unable to finish book archive.');
                $opened = false;
                readfile($path);
            } finally {
                if ($opened) $zip->close();
                if (is_file($path)) unlink($path);
            }
        }, $filename, ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store']);
    }
}
