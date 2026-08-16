<?php

declare(strict_types=1);

namespace Govyx\Rankor;

use Govyx\Core\App;

/**
 * Secure C bridge (Section 40): PHP <-> C service over stdin/stdout pipe.
 * Rankor requests run through the C engine where available;
 * strict input validation, no shell command construction from HTTP input.
 */
final class CScoreClient
{
    private static ?string $binary = null;

    public static function binary(): ?string
    {
        if (self::$binary !== null) {
            return self::$binary === '' ? null : self::$binary;
        }
        $candidates = [
            dirname(__DIR__, 2) . '/c/rankor/rankor_score',
            '/usr/local/bin/rankor_score',
            '/usr/bin/rankor_score',
        ];
        foreach ($candidates as $cand) {
            if (is_executable($cand)) {
                self::$binary = $cand;
                return $cand;
            }
        }
        self::$binary = '';
        return null;
    }

    /** Score a batch of tasks for delay using the C engine. Returns [] on failure. */
    public static function delayScores(array $tasks): array
    {
        $bin = self::binary();
        if ($bin === null) {
            return [];
        }
        $payload = [];
        foreach ($tasks as $t) {
            $payload[] = [
                'id'           => (int) $t['id'],
                'days_overdue' => (int) ($t['days_overdue'] ?? 0),
                'has_deadline' => (int) ($t['deadline'] !== null ? 1 : 0),
                'progress'     => (int) ($t['progress'] ?? 0),
                'priority'     => (string) ($t['priority'] ?? 'medium'),
            ];
        }
        $json = json_encode(['mode' => 'delay', 'tasks' => $payload], JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [];
        }

        $proc = proc_open(
            [$bin],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($proc)) {
            return [];
        }
        fwrite($pipes[0], $json);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        if ($err !== '') {
            error_log('C rankor stderr: ' . substr($err, 0, 500));
        }
        $decoded = json_decode((string) $out, true);
        return is_array($decoded['scores'] ?? null) ? $decoded['scores'] : [];
    }

    public static function available(): bool
    {
        return self::binary() !== null;
    }
}