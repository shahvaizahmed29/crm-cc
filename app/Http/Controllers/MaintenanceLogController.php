<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $expectedPassword = (string) config('services.maintenance_logs.password', '');
        $providedPassword = (string) $request->query('password', '');

        if ($expectedPassword === '' || ! hash_equals($expectedPassword, $providedPassword)) {
            abort(403, 'Invalid password.');
        }

        $tail = max(1, min(5000, (int) $request->query('tail', 200)));
        $requestedType = $this->normalizeType((string) $request->query('type', 'all'));
        $logPath = $this->resolveLogFilePath();
        if ($logPath === null || ! is_readable($logPath)) {
            return response()->json([
                'file' => $logPath,
                'type' => $requestedType,
                'tail' => $tail,
                'count' => 0,
                'lines' => [],
                'error' => 'Log file not found or not readable.',
            ], 404);
        }

        $lines = file($logPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return response()->json([
                'file' => $logPath,
                'type' => $requestedType,
                'tail' => $tail,
                'count' => 0,
                'lines' => [],
                'error' => 'Unable to read log file.',
            ], 500);
        }

        $filtered = $requestedType === 'all'
            ? $lines
            : array_values(array_filter($lines, fn (string $line): bool => $this->lineMatchesType($line, $requestedType)));

        $result = array_slice($filtered, -1 * $tail);

        return response()->json([
            'file' => $logPath,
            'type' => $requestedType,
            'tail' => $tail,
            'count' => count($result),
            'lines' => array_values($result),
        ]);
    }

    private function lineMatchesType(string $line, string $type): bool
    {
        if (! preg_match('/\]\s+[a-zA-Z0-9_.-]+\.(\w+):/', $line, $matches)) {
            return false;
        }

        return strtolower((string) ($matches[1] ?? '')) === $type;
    }

    private function normalizeType(string $type): string
    {
        $value = strtolower(trim($type));
        if ($value === '' || $value === 'all') {
            return 'all';
        }

        $aliases = [
            'errpr' => 'error',
            'err' => 'error',
            'warn' => 'warning',
            'warnint' => 'warning',
        ];

        return $aliases[$value] ?? $value;
    }

    private function resolveLogFilePath(): ?string
    {
        $default = storage_path('logs/laravel.log');
        if (is_file($default)) {
            return $default;
        }

        $files = glob(storage_path('logs/*.log')) ?: [];
        if ($files === []) {
            return null;
        }

        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0] ?? null;
    }
}
