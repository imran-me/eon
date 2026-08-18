<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DmApiService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.dm.url', ''), '/');
    }

    /**
     * Fetch company access / subscription items from DM API.
     * The configured `DM_API_URL` may point to a full endpoint (e.g. https://dm.example.com/api/dm/company-access)
     * Accepts pagination via `page` and `per_page` query params.
     *
     * @param int $page
     * @param int $perPage
     * @param array $filters Extra query params, e.g. ['from' => 'Y-m-d', 'to' => 'Y-m-d']
     * @return array|null
     */
    public function fetchAccessItems(int $page = 1, int $perPage = 10, array $filters = []): ?array
    {
        return $this->fetchItems('/api/dm/company-access', 'company-access.json', $page, $perPage, $filters);
    }

    /**
     * Fetch expired documents from DM API.
     *
     * @param int $page
     * @param int $perPage
     * @param array $filters Extra query params, e.g. ['from' => 'Y-m-d', 'to' => 'Y-m-d']
     * @return array|null
     */
    public function fetchExpiredDocuments(int $page = 1, int $perPage = 10, array $filters = []): ?array
    {
        return $this->fetchItems('/api/dm/expired-documents', 'expired-documents.json', $page, $perPage, $filters);
    }

    /**
     * Fetch a DM endpoint and normalize the response to a plain list.
     *
     * @param string $endpointPath
     * @param string $testFileName
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array|null
     */
    private function fetchItems(string $endpointPath, string $testFileName, int $page = 1, int $perPage = 10, array $filters = []): ?array
    {
        // For testing: read from file if env var is set
        if (env('DM_USE_FILE_DATA', false)) {
            return $this->loadTestDataFromFile($testFileName);
        }

        $requestUrl = $this->resolveEndpointUrl($endpointPath);

        if (empty($requestUrl)) {
            // logger()->warning('DM API URL is empty or not configured.');
            return null;
        }

        try {
            $token = config('services.dm.token');
            
            if (empty($token)) {
                // logger()->warning('DM_API_TOKEN is empty or not configured.');
                return null;
            }

            $resp = Http::withToken($token)
                ->acceptJson()
                ->get($requestUrl, array_merge([
                    'page' => $page,
                    'per_page' => $perPage,
                ], array_filter($filters, fn ($value) => $value !== null && $value !== '')));

            // Log response details for debugging
            // logger()->info('DM API Response', [
            //     'status' => $resp->status(),
            //     'url' => $requestUrl,
            //     'token' => substr($token, 0, 10) . '***', // log partial token
            //     'body' => $resp->body(),
            // ]);

            if ($resp->successful()) {
                $json = $resp->json();
                // logger()->info('DM API Success Response', ['data' => $json]);

                // Supported shapes:
                // 1) { data: [ ...records... ] }
                // 2) { current_page: ..., data: [ ...records... ], ...pagination... }
                // 3) { data: { current_page: ..., data: [ ...records... ], ...pagination... } }
                $records = data_get($json, 'data.data');

                if (is_array($records)) {
                    return $records;
                }

                $records = data_get($json, 'data');
                if (is_array($records) && array_is_list($records)) {
                    return $records;
                }

                if (is_array($json) && array_is_list($json)) {
                    return $json;
                }

                return [];
            } else {
                // logger()->error('DM API failed with status ' . $resp->status(), [
                //     'response' => $resp->body(),
                // ]);
            }
        } catch (\Throwable $e) {
            // logger()->warning('DM API fetch exception: ' . $e->getMessage(), [
            //     'exception' => $e,
            // ]);
        }

        return null;
    }

    /**
     * Resolve the configured DM endpoint URL for the given path.
     * Supports base URL (e.g., http://dm.test/api/dm) and appends the endpoint path.
     */
    private function resolveEndpointUrl(string $endpointPath): string
    {
        if (empty($this->baseUrl)) {
            return '';
        }

        return rtrim($this->baseUrl, '/') . $endpointPath;
    }


    /**
     * Load test data from JSON file for testing/development
     * @return array|null
     */
    private function loadTestDataFromFile(string $fileName): ?array
    {
        $filePath = app_path('Services/' . ltrim($fileName, '/'));
        
        if (!file_exists($filePath)) {
            Log::warning("Test data file not found: {$filePath}");
            return null;
        }

        try {
            $content = file_get_contents($filePath);
            $json = json_decode($content, true);
            
            if (!$json) {
                Log::error('Failed to parse JSON from test file');
                return null;
            }

            // Extract the data array from the paginated response
            return data_get($json, 'data.data') ?? data_get($json, 'data') ?? $json;
        } catch (\Throwable $e) {
            Log::error('Error reading test file: ' . $e->getMessage());
            return null;
        }
    }
}
