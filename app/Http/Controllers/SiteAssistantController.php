<?php

namespace App\Http\Controllers;

use App\Services\GroqSiteAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SiteAssistantController extends Controller
{
    public function __invoke(Request $request, GroqSiteAssistant $assistant): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'messages' => ['required', 'array', 'min:1', 'max:8'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Please send a short question.', 'errors' => $validator->errors()], 422);
        }

        $messages = array_map(
            static fn (array $message): array => ['role' => $message['role'], 'content' => trim($message['content'])],
            $validator->validated()['messages'],
        );

        if ($messages[array_key_last($messages)]['role'] !== 'user') {
            return response()->json(['message' => 'Send a question to continue.'], 422);
        }

        try {
            return response()->json(['reply' => $assistant->reply($messages)]);
        } catch (Throwable $exception) {
            Log::warning('Caldren website assistant request failed.', ['exception' => $exception::class]);

            return response()->json([
                'message' => 'The Caldren assistant is temporarily unavailable. Please try again or email hello@caldren.net.',
            ], 503);
        }
    }
}
