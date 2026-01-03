<?php
// app/Services/External/OpenAIService.php
namespace App\Services\External;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Transcribe audio using Whisper API فقط
     */
    public function transcribeAudio(string $audioPath): string
    {
        try {
            Log::info('Transcribing audio with Whisper API');

            $response = $this->client->post('audio/transcriptions', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($audioPath, 'r'),
                        'filename' => 'audio.mp3',
                    ],
                    [
                        'name' => 'model',
                        'contents' => 'whisper-1',
                    ],
                    [
                        'name' => 'response_format',
                        'contents' => 'json',
                    ],
                    [
                        'name' => 'language',
                        'contents' => 'en',
                    ],
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (!isset($data['text'])) {
                throw new \Exception('No transcription text in response');
            }

            return trim($data['text']);
        } catch (\Exception $e) {
            Log::error('Whisper API transcription failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // نحذف باقي الـ methods (chat completion, etc.)
}
