<?php

namespace Bref\LaravelBridge\Http;

use Bref\Context\Context;
use Bref\Event\Http\Psr7Bridge;
use Bref\Event\Http\HttpRequestEvent;
use Bref\LaravelBridge\Support\MultipartArray;
use Illuminate\Support\Arr;
use Riverline\MultiPartParser\Part;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Request;

class SymfonyRequestBridge
{
    /**
     * Convert Bref HTTP event to Symfony request.
     *
     * @param  \Bref\Event\Http\HttpRequestEvent  $event
     * @param  \Bref\Context\Context  $context
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public static function convertRequest(HttpRequestEvent $event, Context $context): Request
    {
        $psr7Request = Psr7Bridge::convertRequest($event, $context);
        $httpFoundationFactory = new HttpFoundationFactory();
        $symfonyRequest = $httpFoundationFactory->createRequest($psr7Request);

        $symfonyRequest->server->add([
            'HTTP_X_REQUEST_ID' => $context->getAwsRequestId(),
            'LAMBDA_INVOCATION_CONTEXT' => json_encode($context),
            'LAMBDA_REQUEST_CONTEXT' => json_encode($event->getRequestContext()),
        ]);

        $contentType = $event->getContentType();

        if ($event->getMethod() === 'POST' && $contentType !== null && str_starts_with(strtolower($contentType), 'multipart/form-data')) {
            $symfonyRequest->request->replace(self::fixMultipartArrayStructure($event, $contentType));
        }

        return $symfonyRequest;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fixMultipartArrayStructure(HttpRequestEvent $event, string $contentType): array
    {
        $document = new Part("Content-type: $contentType\r\n\r\n".$event->getBody());

        if (! $document->isMultiPart()) {
            return [];
        }

        $fixed = [];

        foreach ($document->getParts() as $part) {
            if ($part->isFile()) {
                continue;
            }

            $name = $part->getName();

            $fixed = str_contains($name, '[')
                ? MultipartArray::setMultiPartArrayValue($fixed, $name, $part->getBody())
                : Arr::set($fixed, $name, $part->getBody());
        }

        return $fixed;
    }
}
