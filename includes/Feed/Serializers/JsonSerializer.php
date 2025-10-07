<?php
namespace OAPFW\Feed\Serializers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * JSON serializer
 */
class JsonSerializer extends AbstractSerializer
{
    public function serialize(array $data): string
    {
        return wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function getContentType(): string
    {
        return 'application/json';
    }

    public function getFileExtension(): string
    {
        return 'json';
    }
}