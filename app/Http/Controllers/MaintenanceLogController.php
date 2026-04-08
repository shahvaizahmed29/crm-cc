<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SplFileObject;

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

        $result = $this->tailFilteredLines($logPath, $tail, $requestedType);

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

    /** @return array<int, string> */
    private function tailFilteredLines(string $path, int $tail, string $type): array
    {
        try {
            $file = new SplFileObject($path, 'r');
            $file->seek(PHP_INT_MAX);
            $lastLineIndex = $file->key();
            $collected = [];

            for ($lineIndex = $lastLineIndex; $lineIndex >= 0; $lineIndex--) {
                $file->seek($lineIndex);
                $line = rtrim((string) $file->current(), "\r\n");

                if ($line === '') {
                    continue;
                }

                if ($type !== 'all' && ! $this->lineMatchesType($line, $type)) {
                    continue;
                }

                $collected[] = $line;
                if (count($collected) >= $tail) {
                    break;
                }
            }

            return array_reverse($collected);
        } catch (\Throwable) {
            return [];
        }
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
