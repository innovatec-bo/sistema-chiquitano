<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WorkflowApiClient
 *
 * Reads workflow data from Serebo2's API. This is the read counterpart
 * of WorkflowSyncNotifier (which only writes/notifies changes).
 *
 * Usage:
 *   $workflow = WorkflowApiClient::getOne($projectId);
 *   $page     = WorkflowApiClient::getPaginated($queryParams);
 *
 * Suggested location: application/libraries/WorkflowApiClient.php
 */
class WorkflowApiClient
{
    /**
     * Timeout in seconds for single-project lookups.
     */
    const TIMEOUT = 10;

    /**
     * Timeout in seconds for paginated/list lookups, which can take a
     * bit longer depending on filters and page size.
     */
    const LIST_TIMEOUT = 15;

    /**
     * Fetches a single project's workflow data. This triggers Serebo2 to
     * recalculate the workflow and update the `workflows` table before
     * returning the data — so the result is always fresh.
     *
     * @param int $projectId  id_pro of the project to fetch
     * @return array|null     associative array with workflow data, or null on failure/not found
     */
    public static function getOne(int $projectId) : ?array
    {
        $apiUrl = self::_getBaseUrl() . "/workflows/{$projectId}";

        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
            ],
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        $curlErrno    = curl_errno($ch);
        curl_close($ch);

        $elapsedMs = round((microtime(true) - $startTime) * 1000);

        // ── Failure ────────────────────────────────────────────────────────
        if ($curlError || $httpCode !== 200)
        {
            $reason = $curlError
                ? "cURL error [{$curlErrno}]: {$curlError}"
                : "HTTP {$httpCode}: " . self::_truncate($responseBody);

            log_message(
                'error',
                "[WorkflowApiClient] FAILED — could not fetch project {$projectId} from Serebo2. "
                . "Reason: {$reason}. URL: {$apiUrl}. Elapsed: {$elapsedMs}ms."
            );

            return null;
        }

        $decoded = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['data']))
        {
            log_message(
                'error',
                "[WorkflowApiClient] FAILED — invalid JSON response for project {$projectId}. URL: {$apiUrl}."
            );
            return null;
        }

        log_message(
            'info',
            "[WorkflowApiClient] OK — fetched project {$projectId} from Serebo2 ({$elapsedMs}ms)."
        );

        return $decoded['data'];
    }

    /**
     * Fetches a paginated, filtered list of workflows directly from the
     * `workflows` table on Serebo2 (does NOT trigger a recalculation —
     * this is meant for listings/datatables, not StatusManagement).
     *
     * @param array $queryParams  e.g. ['per_page' => 100, 'page' => 1, 'search' => 'RA.26', 'status' => '10,11']
     * @return array|null         ['data' => [...], 'meta' => [...]] or null on failure
     */
    public static function getPaginated(array $queryParams = []) : ?array
    {
        set_time_limit(300);
		ini_set('memory_limit','1024M');
        $apiUrl = self::_getBaseUrl() . '/workflows';
        if (!empty($queryParams))
        {
            $apiUrl .= '?' . http_build_query($queryParams);
        }

        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::LIST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
            ],
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        $curlErrno    = curl_errno($ch);
        curl_close($ch);

        $elapsedMs = round((microtime(true) - $startTime) * 1000);

        // ── Failure ────────────────────────────────────────────────────────
        if ($curlError || $httpCode !== 200)
        {
            $reason = $curlError
                ? "cURL error [{$curlErrno}]: {$curlError}"
                : "HTTP {$httpCode}: " . self::_truncate($responseBody);

            log_message(
                'error',
                "[WorkflowApiClient] FAILED — could not fetch workflow list from Serebo2. "
                . "Reason: {$reason}. URL: {$apiUrl}. Elapsed: {$elapsedMs}ms."
            );

            return null;
        }

        $decoded = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE)
        {
            log_message(
                'error',
                "[WorkflowApiClient] FAILED — invalid JSON response for workflow list. URL: {$apiUrl}."
            );
            return null;
        }

        log_message(
            'info',
            "[WorkflowApiClient] OK — fetched workflow list from Serebo2 ({$elapsedMs}ms)."
        );

        return $decoded;
    }

    /**
     * Fetches EVERY page of the workflows endpoint matching the given
     * filters and returns the full, flat list of rows.
     *
     * Use this instead of getPaginated() whenever the caller needs the
     * complete result set (e.g. building an Excel export) and doesn't
     * know in advance how many rows will match the filters. Asking
     * Serebo2 for everything in a single huge per_page value risks
     * memory exhaustion on its side — this fetches in safe-sized pages
     * and accumulates them here instead.
     *
     * @param array $queryParams  base query params (filters), without 'page'/'per_page'
     * @param int   $pageSize     how many rows to request per page (default 1500)
     * @return array              full list of workflow rows across all pages
     */
    public static function getAllPages(array $queryParams, int $pageSize = 1500) : array
    {
        set_time_limit(300);
		ini_set('memory_limit','1024M');
        $accumulated = [];
        $page        = 1;

        do
        {
            $params              = $queryParams;
            $params['per_page']  = $pageSize;
            $params['page']      = $page;

            $response = self::getPaginated($params);

            if ($response === null || !isset($response['data']))
            {
                log_message(
                    'error',
                    "[WorkflowApiClient::getAllPages] Stopped at page {$page} — Serebo2 did not return valid data."
                );
                break;
            }

            $accumulated = array_merge($accumulated, $response['data']);

            $currentPage = $response['meta']['current_page'] ?? $page;
            $lastPage    = $response['meta']['last_page']    ?? $page;
            $page++;
        }
        while ($currentPage < $lastPage);

        return $accumulated;
    }

    /**
     * Resolves the Serebo2 API base URL from the environment.
     *
     * @return string
     */
    private static function _getBaseUrl() : string
    {
        $baseUrl = getenv('SISTEMA_CHIQUITANOV2_URL') ?: '';
        return rtrim($baseUrl, '/') . '/api/v1';
    }

    /**
     * Truncates the response body so it doesn't bloat the log.
     *
     * @param string|null $body
     * @return string
     */
    private static function _truncate(?string $body) : string
    {
        if (empty($body))
        {
            return '(empty response)';
        }
        return mb_substr($body, 0, 300);
    }
}