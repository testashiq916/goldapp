<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectResponsiveCss
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $contentType = $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if (!$content) {
            return $response;
        }

        // JS templates may contain literal "</head>" text. Inject only at a
        // real head close tag, not while the parser is inside a script block.
        $pos = $this->findHeadCloseOutsideScript($content);
        if ($pos === false) {
            return $response;
        }

        $cssPath = public_path('css/goldapp-responsive.css');
        if (!is_file($cssPath)) {
            return $response;
        }

        $cssVer  = @filemtime($cssPath) ?: time();
        $baseUrl = rtrim($request->getBaseUrl(), '/');
        $cssUrl  = $baseUrl . '/css/goldapp-responsive.css?v=' . $cssVer;
        $linkTag = "\n    <link rel=\"stylesheet\" href=\"{$cssUrl}\">";

        $content = substr($content, 0, $pos)
            . $linkTag
            . "\n</head>"
            . substr($content, $pos + 7); // 7 = strlen('</head>')

        if ($this->shouldInjectReportTools($request, $content)) {
            $content = $this->injectReportExportScript($request, $content);
        }

        $response->setContent($content);

        return $response;
    }

    private function findHeadCloseOutsideScript(string $content): int|false
    {
        $offset = 0;
        while (($pos = stripos($content, '</head>', $offset)) !== false) {
            $before = substr($content, 0, $pos);
            $lastScriptOpen = strripos($before, '<script');
            $lastScriptClose = strripos($before, '</script>');

            if ($lastScriptOpen === false || ($lastScriptClose !== false && $lastScriptClose > $lastScriptOpen)) {
                return $pos;
            }

            $offset = $pos + 7;
        }

        return false;
    }

    private function shouldInjectReportTools(Request $request, string $content): bool
    {
        $path = trim($request->path(), '/');
        $reportPrefixes = [
            'reports',
            'order-report',
            'item-reports',
            'barcode-list',
            'deposit-reports',
            'staff-reports',
            'tax-report',
            'tds-report',
        ];

        foreach ($reportPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return str_contains($content, 'id="btnSaveAs"')
            || str_contains($content, "id='btnSaveAs'")
            || str_contains($content, 'ReportExport.init');
    }

    private function injectReportExportScript(Request $request, string $content): string
    {
        if (str_contains($content, 'report-export.js')) {
            return $content;
        }

        $pos = $this->findBodyCloseOutsideScript($content);
        if ($pos === false) {
            return $content;
        }

        $jsPath = public_path('js/report-export.js');
        if (!is_file($jsPath)) {
            return $content;
        }

        $jsVer = @filemtime($jsPath) ?: time();
        $baseUrl = rtrim($request->getBaseUrl(), '/');
        $jsUrl = $baseUrl . '/js/report-export.js?v=' . $jsVer;
        $scriptTag = "\n    <script src=\"{$jsUrl}\"></script>\n";

        return substr($content, 0, $pos)
            . $scriptTag
            . substr($content, $pos);
    }

    private function findBodyCloseOutsideScript(string $content): int|false
    {
        $offset = 0;
        while (($pos = stripos($content, '</body>', $offset)) !== false) {
            $before = substr($content, 0, $pos);
            $lastScriptOpen = strripos($before, '<script');
            $lastScriptClose = strripos($before, '</script>');

            if ($lastScriptOpen === false || ($lastScriptClose !== false && $lastScriptClose > $lastScriptOpen)) {
                return $pos;
            }

            $offset = $pos + 7;
        }

        return false;
    }
}
