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
            $url = $this->baseUrl . '/v1/extract/image/text?lang=deu&api_key=' . $this->apiKey;

            Log::info('OCR Image extraction request', [
                'url' => $url,
                'file' => basename($filePath),
                'field_name' => 'images'
            ]);

            $response = $this->sendFileRequest($url, [$filePath], 'images');

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
                'field_name' => 'file'
            ]);

            $response = $this->sendPdfRequest($url, $filePath);

            return $this->handlePdfResponse($response);
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
            $url = $this->baseUrl . '/v1/extract/image/text?lang=deu&api_key=' . urlencode($this->apiKey);

            Log::info('OCR Multiple images extraction request', [
                'url' => $url,
                'files_count' => count($filePaths),
                'field_name' => 'images[]'
            ]);

            $response = $this->sendFileRequest($url, $filePaths, 'images');

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
     * Send file request with proper array format (for images)
     */
    private function sendFileRequest(string $url, array $filePaths, string $fieldName): Response
    {
        $http = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
        ]);

        foreach ($filePaths as $index => $filePath) {
            $fileName = basename($filePath);
            $fileContent = file_get_contents($filePath);
            $mimeType = mime_content_type($filePath);

            // For single file, use field name without array notation
            $attachFieldName = count($filePaths) === 1 ? $fieldName : "{$fieldName}[{$index}]";

            $http->attach(
                $attachFieldName,
                $fileContent,
                $fileName,
                ['Content-Type' => $mimeType]
            );
        }

        return $http->post($url);
    }

    /**
     * Send PDF request with different format (for PDFs)
     */
    private function sendPdfRequest(string $url, string $filePath): Response
    {
        $http = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
        ]);

        $fileName = basename($filePath);
        $fileContent = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);

        $http->attach(
            'file',
            $fileContent,
            $fileName,
            ['Content-Type' => $mimeType]
        );

        return $http->post($url);
    }

    /**
     * Handle OCR API response
     */
    private function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            $data = $response->json();

            // Extract text from the files structure
            $text = null;

            if (isset($data['files']) && is_array($data['files'])) {
                // Get the first file's text (assuming single file processing)
                $text = implode("\n\n", array_map(function ($file) {
                    return $file['text'];
                }, $data['files']));

                return [
                    'success' => true,
                    'text' => $text,
                    'confidence' => null,
                    'raw_data' => $data
                ];
            }
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

    /**
     * Handle PDF OCR API response (different format)
     */
    private function handlePdfResponse(Response $response): array
    {
        if ($response->successful()) {
            $data = $response->json();

            // PDF response format might be different
            $text = null;
            $ocrData = $data;

            // Handle PDF-specific response format
            if (isset($data['pages'])) {
                $text = implode("\n\n\n", $data['pages']);
            } else {
                // Log the actual response structure for debugging
                Log::warning('Unexpected PDF OCR response format', ['response' => $data]);
            }

            return [
                'success' => true,
                'text' => $text,
                'confidence' => null,
                'raw_data' => $ocrData
            ];
        }

        Log::error('PDF OCR API request failed', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return [
            'success' => false,
            'text' => '',
            'confidence' => 0,
            'error' => 'PDF OCR API request failed with status: ' . $response->status()
        ];
    }
}
