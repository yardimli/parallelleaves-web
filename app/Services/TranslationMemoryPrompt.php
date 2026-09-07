<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// Shared by the chapter translation request and its read-only prompt preview.
class TranslationMemoryPrompt
{
    public function content($bookId, $userId, string $lastUserMessage): string
    {
        $tmContent = '';
		$words = preg_split('/[\s,.;:!?()"-]+/', strtolower($lastUserMessage), -1, PREG_SPLIT_NO_EMPTY);
		$uniqueWords = array_unique($words);
		$uniqueWords = array_filter($uniqueWords, fn($w) => mb_strlen($w) > 2);

		if (!empty($uniqueWords)) {
			$allMemories = [];
			$maxPairs = 100;

			if ($bookId) {
				foreach ($uniqueWords as $word) {
					if (count($allMemories) >= $maxPairs) {
						break;
					}

					// MODIFIED: Parameterized DB query replaces escaped raw prepare [1]
					$regexpPattern = '[[:<:]]' . $word . '[[:>:]]';
					$memoriesForWord = DB::table('user_books_translation_memory as tm')
						->join('user_books as b', 'tm.book_id', '=', 'b.id')
						->select('tm.id', 'tm.source_sentence', 'tm.edited_target_sentence', 'b.source_language', 'b.target_language')
						->where(function ($query) use ($userId) {
							$query->where('b.user_id', $userId)
								->orWhereRaw('? = 1', [$userId]);
						})
						->where('b.id', $bookId)
						->whereRaw('tm.source_sentence REGEXP ?', [$regexpPattern])
						->get()
						->map(fn($item) => (array)$item)
						->all();

					if (count($memoriesForWord) > 3) {
						continue;
					}

					foreach ($memoriesForWord as $memory) {
						if (count($allMemories) >= $maxPairs) {
							break 2;
						}
						$allMemories[$memory['id']] = $memory;
					}
				}
			}

			if (count($allMemories) < $maxPairs) {
				foreach ($uniqueWords as $word) {
					if (count($allMemories) >= $maxPairs) {
						break;
					}
					$regexpPattern = '[[:<:]]' . $word . '[[:>:]]';

					// MODIFIED: Standard Query Builder execution replacing raw SQL string construction [1]
					$query = DB::table('user_books_translation_memory as tm')
						->join('user_books as b', 'tm.book_id', '=', 'b.id')
						->select('tm.id', 'tm.source_sentence', 'tm.edited_target_sentence', 'b.source_language', 'b.target_language')
						->where(function ($q) use ($userId) {
							$q->where('b.user_id', $userId)
								->orWhereRaw('? = 1', [$userId]);
						});

					if ($bookId) {
						$query->where('b.id', '!=', $bookId);
					}

					$memoriesForWord = $query->whereRaw('tm.source_sentence REGEXP ?', [$regexpPattern])
						->get()
						->map(fn($item) => (array)$item)
						->all();

					if (count($memoriesForWord) > 3) {
						continue;
					}

					foreach ($memoriesForWord as $memory) {
						if (count($allMemories) >= $maxPairs) {
							break 2;
						}
						if (!isset($allMemories[$memory['id']])) {
							$allMemories[$memory['id']] = $memory;
						}
					}
				}
			}

			foreach ($allMemories as $mem) {
				$tmContent .= "<{$mem['source_language']}>{$mem['source_sentence']}</{$mem['source_language']}>\n";
				$tmContent .= "<{$mem['target_language']}>{$mem['edited_target_sentence']}</{$mem['target_language']}>\n";
			}
			$tmContent = trim($tmContent);
		}
        return $tmContent;
    }
}
