<?php

namespace App\Interfaces\Services;

use Illuminate\Http\UploadedFile;

interface VoiceServiceInterface
{
    public function transcribeAudio(UploadedFile $audioFile): string;
    public function cleanTranscript(string $transcript): string;
    public function extractFoodItemsFromVoice(string $transcript): array;
    public function validateAudioFile(UploadedFile $audioFile): bool;
    public function getAudioDuration(UploadedFile $audioFile): int;
    public function convertAudioFormat(UploadedFile $audioFile, string $targetFormat): string;
}