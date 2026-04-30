<?php

namespace App\Http\Middleware;

use App\Support\DatabaseBackupManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RunBackupAutomation
{
    public function __construct(
        private readonly DatabaseBackupManager $backupManager
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$request->session()->has('user_code')) {
            return $response;
        }

        if ($this->shouldCreateImportantSaveBackup($request, $response)) {
            $reason = $this->backupReason($request);

            app()->terminating(function () use ($reason) {
                try {
                    $this->backupManager->runImportantSaveBackup($reason);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        return $response;
    }

    private function shouldCreateImportantSaveBackup(Request $request, Response $response): bool
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        $path = trim($request->path(), '/');
        if ($path === '') {
            return false;
        }

        if ($this->isIgnoredPath($path)) {
            return false;
        }

        if (!$this->looksLikeImportantWrite($path)) {
            return false;
        }

        return $this->responseLooksSuccessful($response);
    }

    private function isIgnoredPath(string $path): bool
    {
        $ignored = [
            'backup/run',
            'backup/local-disk',
            'backup/local-disk/browser-copy',
            'backup/local-disk/browse',
            'backup/local-disk/save-path',
            'backup/delete',
            'backup/auto-settings',
            'api/customer/check-phone',
            'api/customer/check-idno',
        ];

        foreach ($ignored as $entry) {
            if ($path === $entry) {
                return true;
            }
        }

        return str_contains($path, '/search')
            || str_contains($path, '/retrieve')
            || str_contains($path, '/lookup')
            || str_contains($path, '/check-');
    }

    private function looksLikeImportantWrite(string $path): bool
    {
        foreach (['save', 'delete', 'cancel', 'import', 'sync'] as $keyword) {
            if (str_contains($path, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function responseLooksSuccessful(Response $response): bool
    {
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data)) {
                if (array_key_exists('ok', $data) && !$data['ok']) {
                    return false;
                }
                if (array_key_exists('success', $data) && !$data['success']) {
                    return false;
                }
            }
        }

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    private function backupReason(Request $request): string
    {
        $path = str_replace('/', '-', trim($request->path(), '/'));
        return $path !== '' ? $path : 'important-save';
    }
}
