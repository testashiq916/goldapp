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

        // Find only the FIRST </head> — JS templates inside the page may also
        // contain the literal string "</head>" and a naive str_replace would
        // corrupt those strings (introducing a newline inside a JS string
        // literal causes a SyntaxError that breaks all subsequent JS).
        $pos = stripos($content, '</head>');
        if ($pos === false) {
            return $response;
        }

        $cssUrl  = asset('css/goldapp-responsive.css');
        $linkTag = "\n    <link rel=\"stylesheet\" href=\"{$cssUrl}\">";

        $content = substr($content, 0, $pos)
            . $linkTag
            . "\n</head>"
            . substr($content, $pos + 7); // 7 = strlen('</head>')

        $response->setContent($content);

        return $response;
    }
}
