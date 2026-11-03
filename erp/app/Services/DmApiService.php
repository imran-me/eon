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
     * Push a subscription's new expiry back into DM.
     *
     * ── THE ONE WRITE THIS SERVICE MAKES ──────────────────────────────────────
     * Everything else here reads. This moves a date that every renewal reminder
     * in the group is driven off, so it is deliberately narrow: it renews one
     * record to one stated date and can do nothing else.
     *
     * ── IT NEVER THROWS ───────────────────────────────────────────────────────
     * The caller is ExpenseController::approve(), which has already written a
     * journal and committed it. Money moved; that fact cannot be undone because
     * another server was unreachable. So every failure — no URL, no token, a
     * refused ability, a timeout, a 500 — comes back as a described result and
     * the caller records it for someone to retry. Nothing propagates.
     *
     * ── ABSOLUTE DATE, NOT "ADD A YEAR" ───────────────────────────────────────
     * The new expiry is computed here and sent as a date, and DM skips the write
     * when it is already at or past it. That is what makes a retry safe: a
     * timeout tells us nothing about whether the write landed, so the same call
     * has to be repeatable without pushing the term out twice.
     *
     * @param  int|string  $id            DM company_access id
     * @param  string      $newExpiry     Y-m-d the term now runs to
     * @param  string|null $newTermStart  Y-m-d the new term began, when known
     * @param  string|null $note          appended to DM's notes for the audit trail
     * @return array{ok: bool, changed: bool, message: string, data: array|null}
     */
    public function renewSubscription($id, string $newExpiry, ?string $newTermStart = null, ?string $note = null): array
    {
        $fail = fn (string $message) => ['ok' => false, 'changed' => false, 'message' => $message, 'data' => null];

        if (env('DM_USE_FILE_DATA', false)) {
            // The desk is reading sample files, so there is no DM to write to.
            // Refused loudly rather than reported as success: a green tick here
            // would claim a live register had been updated.
            return $fail('DM_USE_FILE_DATA is on, so there is no live DM to renew against.');
        }

        $url = $this->resolveEndpointUrl('/api/dm/company-access/' . rawurlencode((string) $id) . '/renew');

        if (empty($url)) {
            return $fail('DM_API_URL is not configured, so the renewal could not be sent.');
        }

        $token = config('services.dm.token');

        if (empty($token)) {
            return $fail('DM_API_TOKEN is not configured, so the renewal could not be sent.');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                // Short, because this runs inside a web request that has already
                // done its real work. A slow DM must not hold the response open.
                ->timeout(10)
                ->connectTimeout(5)
                ->patch($url, array_filter([
                    'expired_date' => $newExpiry,
                    'renewal_date' => $newTermStart,
                    'note' => $note,
                ], fn ($value) => $value !== null && $value !== ''));

            $body = $response->json();

            if ($response->successful()) {
                return [
                    'ok' => true,
                    // DM reports false when the date was already at or past the
                    // one requested. Still a success — the register is correct,
                    // which is all the caller needs to know.
                    'changed' => (bool) ($body['changed'] ?? false),
                    'message' => (string) ($body['message'] ?? 'Renewed in DM.'),
                    'data' => is_array($body['data'] ?? null) ? $body['data'] : null,
                ];
            }

            if ($response->status() === 403) {
                // Almost always the cause: the token predates the renew ability.
                // Named explicitly because the fix is not obvious — abilities are
                // fixed at mint time, so the token has to be reissued.
                return $fail(
                    'DM refused the renewal (403). The API token most likely lacks the '
                    . '"company-access:renew" ability — abilities cannot be added to an existing '
                    . 'token, so a new one must be issued and DM_API_TOKEN updated.'
                );
            }

            return $fail(
                'DM refused the renewal (HTTP ' . $response->status() . ')'
                . (! empty($body['message']) ? ': ' . $body['message'] : '.')
            );
        } catch (\Throwable $e) {
            Log::warning('DM renewal push failed: ' . $e->getMessage(), ['company_access_id' => $id]);

            return $fail('Could not reach DM: ' . $e->getMessage());
        }
    }

    /**
     * Record a document's new term in DM.
     *
     * ── WHY THIS POSTS INSTEAD OF PATCHING ────────────────────────────────────
     * Renewing a subscription moves one row's date. Renewing a DOCUMENT adds a
     * row: DM keeps one per term, so the licence you held last year and the one
     * you hold now are two records, and the history is the point. Moving the date
     * instead would erase the term that just ended.
     *
     * ── THE EXPIRY IS GIVEN, NEVER GUESSED ────────────────────────────────────
     * DM states no cycle for documents and its dates cannot be used to infer one
     * — the same folder holds rows where the term starts and ends on the same day,
     * one ending in 2050, one beginning in the year 0025. So the new expiry comes
     * from the person filing the expense, who confirmed it on the form.
     *
     * ── IT NEVER THROWS ───────────────────────────────────────────────────────
     * Same contract as renewSubscription(): the journal is already committed by
     * the time this runs, so every failure comes back described and gets recorded
     * for a retry. Nothing propagates.
     *
     * @param  int|string  $id           DM multiple_documents id being renewed
     * @param  string      $newExpiry    Y-m-d the new term runs to
     * @param  string|null $termStart    Y-m-d it begins; DM defaults it sensibly
     * @return array{ok: bool, changed: bool, message: string, data: array|null}
     */
    public function renewDocument($id, string $newExpiry, ?string $termStart = null): array
    {
        $fail = fn (string $message) => ['ok' => false, 'changed' => false, 'message' => $message, 'data' => null];

        if (env('DM_USE_FILE_DATA', false)) {
            return $fail('DM_USE_FILE_DATA is on, so there is no live DM to renew against.');
        }

        $url = $this->resolveEndpointUrl('/api/dm/expired-documents/' . rawurlencode((string) $id) . '/renew');

        if (empty($url)) {
            return $fail('DM_API_URL is not configured, so the renewal could not be sent.');
        }

        $token = config('services.dm.token');

        if (empty($token)) {
            return $fail('DM_API_TOKEN is not configured, so the renewal could not be sent.');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->post($url, array_filter([
                    'renewal_date' => $newExpiry,
                    'created_date' => $termStart,
                ], fn ($value) => $value !== null && $value !== ''));

            $body = $response->json();

            if ($response->successful()) {
                return [
                    'ok' => true,
                    // false means DM already held that term — a retry landing
                    // twice, or somebody added it there first. Still a success.
                    'changed' => (bool) ($body['created'] ?? false),
                    'message' => (string) ($body['message'] ?? 'Renewed in DM.'),
                    'data' => is_array($body['data'] ?? null) ? $body['data'] : null,
                ];
            }

            if ($response->status() === 403) {
                return $fail(
                    'DM refused the renewal (403). The API token most likely lacks the '
                    . '"documents:renew" ability — abilities are fixed when a token is created, '
                    . 'so a new one must be issued (or the ability added to the existing row) '
                    . 'and DM_API_TOKEN updated.'
                );
            }

            return $fail(
                'DM refused the renewal (HTTP ' . $response->status() . ')'
                . (! empty($body['message']) ? ': ' . $body['message'] : '.')
            );
        } catch (\Throwable $e) {
            Log::warning('DM document renewal push failed: ' . $e->getMessage(), ['multiple_document_id' => $id]);

            return $fail('Could not reach DM: ' . $e->getMessage());
        }
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
