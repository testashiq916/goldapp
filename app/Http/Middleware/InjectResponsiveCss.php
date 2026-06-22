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
}
