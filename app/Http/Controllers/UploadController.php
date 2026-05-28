<?php

	namespace App\Http\Controllers;

	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;

	class UploadController extends Controller
	{
		public function __invoke(Request $request): JsonResponse
		{
			if (!Auth::check()) {
				return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
			}

			if (!$request->hasFile('file')) {
				return response()->json(['success' => false, 'message' => 'No file uploaded']);
			}

			$file = $request->file('file');

			if (!$file->isValid()) {
				return response()->json(['success' => false, 'message' => 'The uploaded file is invalid.']);
			}

			$safeFilename = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $file->getClientOriginalName());
			$filename = time() . '-' . $safeFilename;
			$targetDir = storage_path('app/public/userData/temp');

			if (!is_dir($targetDir)) {
				mkdir($targetDir, 0755, true);
			}

			$file->move($targetDir, $filename);

			return response()->json([
				'success' => true,
				'filePath' => $targetDir . DIRECTORY_SEPARATOR . $filename,
				'url' => '/storage/userData/temp/' . $filename,
			]);
		}
	}
