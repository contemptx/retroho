<?php

namespace App\Helpers;

class NzbParser
{
    public function parse(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if (!$content) {
            return [];
        }

        $xml = simplexml_load_string($content);
        if (!$xml) {
            return [];
        }

        $files = [];
        foreach ($xml->file as $fileElem) {
            $attributes = $fileElem->attributes();

            $groups = [];
            if (isset($fileElem->groups->group)) {
                foreach ($fileElem->groups->group as $group) {
                    $groups[] = (string) $group;
                }
            }

            $segments = [];
            if (isset($fileElem->segments->segment)) {
                foreach ($fileElem->segments->segment as $segment) {
                    $segments[] = [
                        'number' => (string) $segment->attributes()->number,
                        'article' => (string) $segment->attributes()->article,
                        'bytes' => (string) $segment->attributes()->bytes,
                        'content' => (string) $segment,
                    ];
                }
            }

            $files[] = [
                'subject' => (string) $attributes->subject,
                'poster' => (string) $attributes->poster,
                'date' => (string) $attributes->date,
                'groups' => $groups,
                'segments' => $segments,
            ];
        }

        return [
            'files' => $files,
            'totalFiles' => count($files),
            'totalSegments' => array_sum(array_map(fn($file) => count($file['segments']), $files)),
        ];
    }
}
