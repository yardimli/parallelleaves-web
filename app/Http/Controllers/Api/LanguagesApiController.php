<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

require_once __DIR__ . '/ApiSupport.php';

class LanguagesApiController extends Controller
{
    public function supported(Request $request): JsonResponse
    {
        try {
            $channel = 'languages:get-supported';
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
                                        $result = [
                                            'af' => 'Afrikaans',
                                            'bg' => 'Bulgarian',
                                            'ca' => 'Catalan',
                                            'zh-CN' => 'Chinese (Simplified)',
                                            'zh-TW' => 'Chinese (Traditional)',
                                            'cs' => 'Czech',
                                            'cy' => 'Welsh',
                                            'da' => 'Danish',
                                            'de' => 'German',
                                            'el' => 'Greek',
                                            'en-GB' => 'English (UK)',
                                            'en-US' => 'English (US)',
                                            'es-419' => 'Spanish (Latin America)',
                                            'es-AR' => 'Spanish (Argentina)',
                                            'es-ES' => 'Spanish (Spain)',
                                            'es-MX' => 'Spanish (Mexico)',
                                            'es-US' => 'Spanish (US)',
                                            'et' => 'Estonian',
                                            'fa' => 'Persian',
                                            'fo' => 'Faroese',
                                            'fr' => 'French',
                                            'he' => 'Hebrew',
                                            'hi' => 'Hindi',
                                            'hr' => 'Croatian',
                                            'hu' => 'Hungarian',
                                            'hy' => 'Armenian',
                                            'id' => 'Indonesian',
                                            'it' => 'Italian',
                                            'ja' => 'Japanese',
                                            'ko' => 'Korean',
                                            'lt' => 'Lithuanian',
                                            'lv' => 'Latvian',
                                            'nb' => 'Norwegian (Bokmål)',
                                            'nn' => 'Norwegian (Nynorsk)',
                                            'nl' => 'Dutch',
                                            'pl' => 'Polish',
                                            'pt-BR' => 'Portuguese (Brazil)',
                                            'pt-PT' => 'Portuguese (Portugal)',
                                            'ro' => 'Romanian',
                                            'ru' => 'Russian',
                                            'sh' => 'Serbo-Croatian',
                                            'sk' => 'Slovak',
                                            'sl' => 'Slovenian',
                                            'sq' => 'Albanian',
                                            'sr' => 'Serbian',
                                            'sv' => 'Swedish',
                                            'ta' => 'Tamil',
                                            'tg' => 'Tajik',
                                            'tr' => 'Turkish',
                                            'uk' => 'Ukrainian',
                                            'vi' => 'Vietnamese',
                                        ];
                                        break;
                
                                    // --- Books ---
            } while (false);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
}
