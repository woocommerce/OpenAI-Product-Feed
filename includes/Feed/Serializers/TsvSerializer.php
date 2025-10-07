<?php
namespace OAPFW\Feed\Serializers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * TSV (Tab-Separated Values) serializer
 */
class TsvSerializer extends AbstractSerializer
{
    public function serialize(array $data): string
    {
        if (!$data) {
            return '';
        }

        $handle = fopen('php://temp', 'w+');
        
        // Write header
        fwrite($handle, implode("\t", array_keys($data[0])) . "\n");
        
        // Write data rows
        foreach ($data as $row) {
            fwrite($handle, implode("\t", $this->stringifyValues($row)) . "\n");
        }
        
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        
        return $content;
    }

    public function getContentType(): string
    {
        return 'text/tab-separated-values';
    }

    public function getFileExtension(): string
    {
        return 'tsv';
    }
}