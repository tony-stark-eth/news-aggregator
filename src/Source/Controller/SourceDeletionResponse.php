<?php

declare(strict_types=1);

namespace App\Source\Controller;

use Symfony\Component\HttpFoundation\Response;

final class SourceDeletionResponse
{
    public static function success(bool $isHtmx, string $redirectUrl): Response
    {
        if ($isHtmx) {
            return new Response('');
        }

        return new Response('', Response::HTTP_FOUND, [
            'Location' => $redirectUrl,
        ]);
    }

    public static function error(bool $isHtmx, string $message, string $redirectUrl): Response
    {
        if ($isHtmx) {
            return new Response(
                self::buildFeedbackHtml('error', $message),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return new Response('', Response::HTTP_FOUND, [
            'Location' => $redirectUrl,
        ]);
    }

    public static function refresh(bool $isHtmx, string $redirectUrl): Response
    {
        if ($isHtmx) {
            return new Response('', Response::HTTP_OK, [
                'HX-Refresh' => 'true',
            ]);
        }

        return new Response('', Response::HTTP_FOUND, [
            'Location' => $redirectUrl,
        ]);
    }

    private static function buildFeedbackHtml(string $level, string $message): string
    {
        $alertClass = $level === 'error' ? 'alert-error' : 'alert-success';

        return sprintf(
            '<div id="sources-delete-feedback" hx-swap-oob="innerHTML" class="alert %s shadow-sm mb-4"><span>%s</span></div>',
            $alertClass,
            htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }
}
