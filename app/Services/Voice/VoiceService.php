<?php

namespace App\Services\Voice;

use App\Interfaces\Services\VoiceServiceInterface;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;

class VoiceService implements VoiceServiceInterface
{
    private array $supportedFormats = ['mp3', 'wav', 'm4a', 'ogg'];
    private int $maxFileSize = 10485760; // 10MB
    private int $maxDuration = 300; // 5 minutes

    // public function __construct(OpenAIService $openAIService)
    // {
    //     $this->openAIService = $openAIService;
    // }

    /**
     * Transcribe audio file to text
     */
    // public function transcribeAudio(UploadedFile $audioFile): string
    // {
    //     $this->validateAudioFile($audioFile);

    //     Log::info('Starting audio transcription', [
    //         'filename' => $audioFile->getClientOriginalName(),
    //         'size' => $audioFile->getSize(),
    //         'mime_type' => $audioFile->getMimeType()
    //     ]);

    //     try {
    //         // Convert to MP3 if needed
    //         $convertedPath = $this->convertAudioFormat($audioFile, 'mp3');

    //         // Transcribe using OpenAI Whisper
    //         // $transcript = $this->openAIService->transcribeAudio($convertedPath);

    //         // Clean up temporary file
    //         if ($convertedPath !== $audioFile->getRealPath()) {
    //             unlink($convertedPath);
    //         }

    //         // Clean and normalize transcript
    //         // $cleanTranscript = $this->cleanTranscript($transcript);

    //         Log::info('Audio transcription completed', [
    //             'original_length' => strlen($transcript),
    //             'cleaned_length' => strlen($cleanTranscript)
    //         ]);

    //         return $cleanTranscript;
    //     } catch (\Exception $e) {
    //         Log::error('Audio transcription failed', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         throw new \Exception("Voice processing failed: " . $e->getMessage());
    //     }
    // }

    /**
     * Clean and normalize transcript
     */
    public function cleanTranscript(string $transcript): string
    {
        $transcript = strtolower(trim($transcript));

        // Remove filler words and phrases
        $fillerWords = [
            'um',
            'uh',
            'like',
            'you know',
            'actually',
            'basically',
            'literally',
            'i mean',
            'sort of',
            'kind of',
            'right',
            'okay',
            'so',
            'well',
            'hmm',
            'ah',
            'er'
        ];

        foreach ($fillerWords as $filler) {
            $transcript = preg_replace('/\b' . preg_quote($filler, '/') . '\b/', '', $transcript);
        }

        // Remove extra spaces and punctuation issues
        $transcript = preg_replace('/\s+/', ' ', $transcript);
        $transcript = preg_replace('/\s*([.,;:!?])\s*/', '$1 ', $transcript);

        // Capitalize first letter
        $transcript = ucfirst(trim($transcript));

        // Remove trailing punctuation if it doesn't make sense
        $transcript = rtrim($transcript, '.,;: ');

        return $transcript;
    }

    /**
     * Extract food items from transcript
     */
    public function extractFoodItemsFromVoice(string $transcript): array
    {
        $foodItems = [];

        // Common food-related patterns
        $patterns = [
            // Quantity + food (e.g., "2 eggs", "three apples")
            '/(\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s+(?:x\s*)?([a-z\s]+?)(?:\.|,|$)/i',

            // Food with quantity after (e.g., "eggs 2", "apple one")
            '/([a-z\s]+?)\s+(\d+|one|two|three|four|five|six|seven|eight|nine|ten)(?:\s|\.|,|$)/i',

            // "I ate X" pattern
            '/\b(?:ate|had|consumed|eaten)\s+([a-z\s]+?)(?:\.|,|$)/i',

            // "X of Y" pattern (e.g., "slice of pizza", "cup of rice")
            '/(\d+|a|an)\s+([a-z]+)\s+of\s+([a-z\s]+?)(?:\.|,|$)/i',
        ];

        $wordNumbers = [
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
            'six' => 6,
            'seven' => 7,
            'eight' => 8,
            'nine' => 9,
            'ten' => 10,
            'a' => 1,
            'an' => 1
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $transcript, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if (count($match) >= 3) {
                        $quantity = $match[1];
                        $food = count($match) === 4 ? $match[3] : $match[2];

                        // Convert word numbers to digits
                        if (isset($wordNumbers[strtolower($quantity)])) {
                            $quantity = $wordNumbers[strtolower($quantity)];
                        } elseif (is_numeric($quantity)) {
                            $quantity = (float)$quantity;
                        } else {
                            $quantity = 1;
                        }

                        $food = trim($food);

                        if (!empty($food) && $this->isLikelyFoodItem($food)) {
                            $foodItems[] = [
                                'quantity' => $quantity,
                                'item' => $food,
                                'original_text' => $match[0],
                                'confidence' => $this->calculateConfidence($food)
                            ];
                        }
                    }
                }
            }
        }

        // If no patterns matched, try to extract standalone food items
        if (empty($foodItems)) {
            $words = explode(' ', $transcript);
            $commonFoods = $this->getCommonFoodList();

            foreach ($words as $word) {
                $word = trim($word, '.,;:!? ');
                if (in_array(strtolower($word), $commonFoods)) {
                    $foodItems[] = [
                        'quantity' => 1,
                        'item' => $word,
                        'original_text' => $word,
                        'confidence' => 0.7
                    ];
                }
            }
        }

        // Remove duplicates
        $uniqueItems = [];
        foreach ($foodItems as $item) {
            $key = strtolower($item['item']);
            if (!isset($uniqueItems[$key])) {
                $uniqueItems[$key] = $item;
            }
        }

        return array_values($uniqueItems);
    }

    /**
     * Validate audio file
     */
    public function validateAudioFile(UploadedFile $audioFile): bool
    {
        // Check file size
        if ($audioFile->getSize() > $this->maxFileSize) {
            throw new \Exception("Audio file too large. Maximum size is " .
                round($this->maxFileSize / 1048576, 1) . "MB");
        }

        // Check file extension
        $extension = strtolower($audioFile->getClientOriginalExtension());
        if (!in_array($extension, $this->supportedFormats)) {
            throw new \Exception("Unsupported audio format. Supported formats: " .
                implode(', ', $this->supportedFormats));
        }

        // Check MIME type
        $mimeType = $audioFile->getMimeType();
        $validMimeTypes = [
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/x-wav',
            'audio/mp4',
            'audio/x-m4a',
            'audio/ogg',
            'audio/webm'
        ];

        if (!in_array($mimeType, $validMimeTypes)) {
            throw new \Exception("Invalid audio file type");
        }

        // Check duration if possible
        try {
            $duration = $this->getAudioDuration($audioFile);
            if ($duration > $this->maxDuration) {
                throw new \Exception("Audio too long. Maximum duration is " .
                    $this->maxDuration . " seconds");
            }
        } catch (\Exception $e) {
            // Duration check failed, but continue anyway
            Log::warning('Could not check audio duration', ['error' => $e->getMessage()]);
        }

        return true;
    }

    /**
     * Get audio file duration in seconds
     */
    public function getAudioDuration(UploadedFile $audioFile): int
    {
        try {
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => config('services.ffmpeg.path', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => config('services.ffprobe.path', '/usr/bin/ffprobe'),
                'timeout' => 60,
            ]);

            $audio = $ffmpeg->open($audioFile->getRealPath());
            $format = $audio->getFormat();

            return (int) ceil($format->get('duration'));
        } catch (\Exception $e) {
            Log::warning('Failed to get audio duration', ['error' => $e->getMessage()]);

            // Estimate based on file size (rough estimate)
            $sizeMB = $audioFile->getSize() / 1048576;
            return (int) ceil($sizeMB * 60); // Rough estimate: 1MB ≈ 1 minute
        }
    }

    /**
     * Convert audio format if needed
     */
    public function convertAudioFormat(UploadedFile $audioFile, string $targetFormat): string
    {
        $originalPath = $audioFile->getRealPath();
        $originalExtension = strtolower($audioFile->getClientOriginalExtension());

        // If already in target format, return original
        if ($originalExtension === $targetFormat) {
            return $originalPath;
        }

        try {
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => config('services.ffmpeg.path', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => config('services.ffprobe.path', '/usr/bin/ffprobe'),
                'timeout' => 120,
            ]);

            $tempPath = tempnam(sys_get_temp_dir(), 'audio_') . '.' . $targetFormat;

            $audio = $ffmpeg->open($originalPath);

            $format = null;
            switch ($targetFormat) {
                case 'mp3':
                    $format = new Mp3();
                    break;
                // Add more formats as needed
                default:
                    throw new \Exception("Unsupported target format: {$targetFormat}");
            }

            $audio->save($format, $tempPath);

            Log::info('Audio format converted', [
                'from' => $originalExtension,
                'to' => $targetFormat,
                'original_size' => filesize($originalPath),
                'converted_size' => filesize($tempPath)
            ]);

            return $tempPath;
        } catch (\Exception $e) {
            Log::error('Audio format conversion failed', [
                'error' => $e->getMessage(),
                'from' => $originalExtension,
                'to' => $targetFormat
            ]);

            // If conversion fails but original is MP3, WAV, or M4A, return original
            if (in_array($originalExtension, ['mp3', 'wav', 'm4a'])) {
                return $originalPath;
            }

            throw new \Exception("Audio conversion failed: " . $e->getMessage());
        }
    }

    /**
     * Helper Methods
     */
    private function isLikelyFoodItem(string $text): bool
    {
        $text = strtolower(trim($text));

        // Check if it's a common non-food word
        $nonFoodWords = [
            'the',
            'and',
            'with',
            'for',
            'that',
            'this',
            'have',
            'from',
            'they',
            'what',
            'when',
            'where',
            'why',
            'how',
            'then',
            'than'
        ];

        if (in_array($text, $nonFoodWords)) {
            return false;
        }

        // Check minimum length
        if (strlen($text) < 2) {
            return false;
        }

        // Check if it contains at least one letter
        if (!preg_match('/[a-z]/', $text)) {
            return false;
        }

        return true;
    }

    private function calculateConfidence(string $foodItem): float
    {
        $foodItem = strtolower($foodItem);
        $commonFoods = $this->getCommonFoodList();

        if (in_array($foodItem, $commonFoods)) {
            return 0.9;
        }

        // Check if it contains food-related words
        $foodKeywords = [
            'egg',
            'bread',
            'rice',
            'pasta',
            'meat',
            'chicken',
            'beef',
            'fish',
            'fruit',
            'vegetable',
            'salad',
            'soup',
            'cheese',
            'milk',
            'yogurt',
            'nut',
            'seed',
            'bean',
            'lentil',
            'potato',
            'tomato',
            'onion',
            'apple',
            'banana',
            'orange',
            'berry',
            'grape',
            'melon'
        ];

        foreach ($foodKeywords as $keyword) {
            if (str_contains($foodItem, $keyword)) {
                return 0.8;
            }
        }

        return 0.5;
    }

    private function getCommonFoodList(): array
    {
        return [
            'apple',
            'banana',
            'orange',
            'grape',
            'strawberry',
            'blueberry',
            'egg',
            'eggs',
            'bread',
            'toast',
            'rice',
            'pasta',
            'noodles',
            'chicken',
            'beef',
            'pork',
            'fish',
            'salmon',
            'tuna',
            'milk',
            'cheese',
            'yogurt',
            'butter',
            'carrot',
            'broccoli',
            'spinach',
            'lettuce',
            'tomato',
            'potato',
            'onion',
            'garlic',
            'pepper',
            'cucumber',
            'water',
            'coffee',
            'tea',
            'juice',
            'soda',
            'chocolate',
            'cookie',
            'cake',
            'ice cream',
            'candy'
        ];
    }

    /**
     * Save audio file for future reference (optional)
     */
    public function saveAudioFile(UploadedFile $audioFile, string $userId): string
    {
        $path = "voices/user_{$userId}/" . date('Y/m/d');
        $filename = uniqid('voice_') . '.' . $audioFile->getClientOriginalExtension();

        $fullPath = Storage::putFileAs($path, $audioFile, $filename);

        Log::info('Audio file saved', [
            'user_id' => $userId,
            'path' => $fullPath,
            'size' => $audioFile->getSize()
        ]);

        return $fullPath;
    }

    /**
     * Process audio in background (async)
     */
    public function processAsync(UploadedFile $audioFile, string $userId): void
    {
        // This would typically dispatch a job
        // For example: ProcessVoiceJob::dispatch($audioFile, $userId);

        // For now, we'll process synchronously
        $transcript = $this->transcribeAudio($audioFile);

        // You could fire an event here
        event(new \App\Events\VoiceProcessed($userId, $transcript));
    }
}
