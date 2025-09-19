<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrService
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiToken;

    public function __construct()
    {
        $this->baseUrl = config('services.ocr.base_url', env('OCR_NEXT_SERVER', 'http://ocr-next-api'));
        $this->apiKey = config('services.ocr.api_key', env('OCR_NEXT_API_KEY'));
        $this->apiToken = config('services.ocr.api_token', env('OCR_NEXT_API_TOKEN'));
    }

    /**
     * Extract text from image file
     */
    public function extractFromImage(string $filePath): array
    {
        try {
            $url = $this->baseUrl . '/v1/extract/image/text?api_key=' . $this->apiKey;

            Log::info('OCR Image extraction request', [
                'url' => $url,
                'file' => basename($filePath),
                'field_name' => 'images[]'
            ]);

            $response = $this->sendFileRequest($url, [$filePath]);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('OCR Image extraction failed', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'text' => '',
                'confidence' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract text from PDF file
     */
    public function extractFromPdf(string $filePath): array
    {
        try {
            $url = $this->baseUrl . '/v1/extract/pdf/text?api_key=' . urlencode($this->apiKey);

            Log::info('OCR PDF extraction request', [
                'url' => $url,
                'file' => basename($filePath),
                'field_name' => 'images[]'
            ]);

            $response = $this->sendFileRequest($url, [$filePath]);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('OCR PDF extraction failed', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'text' => '',
                'confidence' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract text from multiple images
     */
    public function extractFromMultipleImages(array $filePaths): array
    {
        try {
            $url = $this->baseUrl . '/v1/extract/image/text?api_key=' . urlencode($this->apiKey);

            Log::info('OCR Multiple images extraction request', [
                'url' => $url,
                'files_count' => count($filePaths),
                'field_name' => 'images[]'
            ]);

            $response = $this->sendFileRequest($url, $filePaths);

            dd($response->getBody());
            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('OCR Multiple images extraction failed', [
                'files' => $filePaths,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'text' => '',
                'confidence' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send file request with proper array format
     */
    private function sendFileRequest(string $url, array $filePaths): Response
    {
        $multipart = [];

        foreach ($filePaths as $filePath) {
            $multipart[] = [
                'name' => 'images[]',
                'contents' => file_get_contents($filePath),
                'filename' => basename($filePath)
            ];
        }

        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
        ])->attach($multipart)->post($url);
    }

    /**
     * Handle OCR API response
     */
    private function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            $data = $response->json();

            return [
                'success' => true,
                'text' => $data['text'] ?? '',
                'confidence' => $data['confidence'] ?? 0,
                'raw_data' => $data
            ];
        }

        Log::error('OCR API request failed', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return [
            'success' => false,
            'text' => '',
            'confidence' => 0,
            'error' => 'OCR API request failed with status: ' . $response->status()
        ];
    }
}
