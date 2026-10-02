<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WorkflowSyncNotifier
 *
 * Notifies Serebo2 that it should recalculate and update the `workflows`
 * record for a specific project.
 *
 * Behavior:
 *   - Synchronous call via cURL with a short timeout.
 *   - Silent failure: if Serebo2 doesn't respond or responds with an error,
 *     Serebo keeps working normally. The failure is logged so it can be
 *     audited later.
 *
 * Usage:
 *   WorkflowSyncNotifier::notify($projectId);
 *   WorkflowSyncNotifier::notifyMany([123, 456, 789]);
 *
 * Suggested location: application/libraries/WorkflowSyncNotifier.php
 */
class WorkflowSyncNotifier
{
    /**
     * Timeout in seconds for the HTTP call to Serebo2.
     */
    const TIMEOUT = 5;

    /**
     * Longer timeout for batch calls, since Serebo2 has to recalculate
     * multiple projects before responding.
     */
    const BATCH_TIMEOUT = 30;

    /**
     * Notifies Serebo2 to recalculate the workflow for 1 project.
     *
     * @param int $projectId  id_pro of the project to update
     * @return bool           true if Serebo2 responded OK, false if it failed
     */
    public static function notify(int $projectId) : bool
    {
        $apiUrl = self::_getSingleApiUrl($projectId);

        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        $curlErrno    = curl_errno($ch);
        curl_close($ch);

        $elapsedMs = round((microtime(true) - $startTime) * 1000);

        // ── Success ────────────────────────────────────────────────────────
        if (!$curlError && $httpCode === 200)
        {
            log_message(
                'info',
                "[WorkflowSyncNotifier] OK — project {$projectId} updated on Serebo2 ({$elapsedMs}ms)."
            );
            return true;
        }

        // ── Silent failure, but logged ───────────────────────────────────────
        $reason = $curlError
            ? "cURL error [{$curlErrno}]: {$curlError}"
            : "HTTP {$httpCode}: " . self::_truncate($responseBody);

        log_message(
            'error',
            "[WorkflowSyncNotifier] FAILED — project {$projectId} could not be updated on Serebo2. "
            . "Reason: {$reason}. URL: {$apiUrl}. Elapsed: {$elapsedMs}ms."
        );

        return false;
    }

    /**
     * Notifies Serebo2 to recalculate the workflow for multiple projects
     * in a single HTTP call. Use this whenever an action affects several
     * projects at once (e.g. a payment order that covers many projects),
     * instead of looping over notify() one by one.
     *
     * @param int[] $projectIds  list of id_pro values to update
     * @return bool              true if Serebo2 responded OK, false if it failed
     */
    public static function notifyMany(array $projectIds) : bool
    {
        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));

        if (empty($projectIds))
        {
            log_message('info', '[WorkflowSyncNotifier] notifyMany() called with an empty project list — skipped.');
            return true;
        }

        $apiUrl = self::_getBatchApiUrl();
        $payload = json_encode(['projects' => $projectIds]);

        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::BATCH_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        $curlErrno    = curl_errno($ch);
        curl_close($ch);

        $elapsedMs = round((microtime(true) - $startTime) * 1000);
        $count     = count($projectIds);

        // ── Success ────────────────────────────────────────────────────────
        if (!$curlError && $httpCode === 200)
        {
            log_message(
                'info',
                "[WorkflowSyncNotifier] OK — {$count} project(s) updated on Serebo2 ({$elapsedMs}ms). IDs: "
                . implode(',', $projectIds)
            );
            return true;
        }

        // ── Silent failure, but logged ───────────────────────────────────────
        $reason = $curlError
            ? "cURL error [{$curlErrno}]: {$curlError}"
            : "HTTP {$httpCode}: " . self::_truncate($responseBody);

        log_message(
            'error',
            "[WorkflowSyncNotifier] FAILED — batch of {$count} project(s) could not be updated on Serebo2. "
            . "Reason: {$reason}. URL: {$apiUrl}. Elapsed: {$elapsedMs}ms. IDs: " . implode(',', $projectIds)
        );

        return false;
    }

    /**
     * Builds the refresh endpoint URL for a single project.
     *
     * @param int $projectId
     * @return string
     */
    private static function _getSingleApiUrl(int $projectId) : string
    {
        return self::_getBaseUrl() . "/workflows/{$projectId}/refresh";
    }

    /**
     * Builds the batch refresh endpoint URL.
     *
     * @return string
     */
    private static function _getBatchApiUrl() : string
    {
        return self::_getBaseUrl() . '/workflows/refresh';
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