<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Stringable;

class TorrentTools
{
    /**
     * Moves and decodes the torrent.
     *
     * @return array<mixed>>
     */
    public static function normalizeTorrent(UploadedFile $torrentFile)
    {
        $raw = Bencode::bdecode_file($torrentFile->getRealPath());
        $version = Bencode::get_torrent_version($raw);

        $topLevelWhitelist = [
            'comment'    => '',
            'created by' => '',
            'encoding'   => '',
            'info'       => '',
        ];

        if ($version >= 2) {
            $topLevelWhitelist['piece layers'] = '';
        }

        $result = array_intersect_key($raw, $topLevelWhitelist);

        $infoWhitelist = [
            'files'        => '',
            'length'       => '',
            'name'         => '',
            'piece length' => '',
            'pieces'       => '',
        ];

        if ($version >= 2) {
            $infoWhitelist['file tree'] = '';
            $infoWhitelist['meta version'] = '';
        }

        $result['info'] = array_intersect_key($result['info'], $infoWhitelist);

        $result['info']['source'] = config('torrent.source');
        $result['info']['private'] = 1;

        if (config('torrent.created_by_append') && \array_key_exists('created by', $result)) {
            $result['created by'] = trim((string) $result['created by'], '. ').'. '.config('torrent.created_by', '');
        } else {
            $result['created by'] = config('torrent.created_by', '');
        }

        $comment = config('torrent.comment');

        if ($comment !== null) {
            $result['comment'] = $comment;
        }

        return $result;
    }

    /**
     * Returns the torrent file list.
     *
     * @param array<mixed> $decodedTorrent
     * @return array<
     *     int,
     *     array{
     *         name: string,
     *         size: int,
     *     }
     * >
     */
    public static function getTorrentFiles(array $decodedTorrent): array
    {
        $files = [];

        $version = Bencode::get_torrent_version($decodedTorrent);

        if ($version >= 2 && isset($decodedTorrent['info']['file tree'])) {
            self::collectFilesFromTree($decodedTorrent['info']['file tree'], '', $files);
        } elseif (\array_key_exists('files', $decodedTorrent['info']) && (is_countable($decodedTorrent['info']['files']) ? \count($decodedTorrent['info']['files']) : 0)) {
            foreach ($decodedTorrent['info']['files'] as $k => $file) {
                $dir = '';
                $count = is_countable($file['path']) ? \count($file['path']) : 0;

                for ($i = 0; $i < $count; $i++) {
                    if ($i + 1 === $count) {
                        $fname = $dir.$file['path'][$i];
                        $files[$k]['name'] = $fname;
                    } else {
                        $dir .= $file['path'][$i].'/';
                        $files[$k]['name'] = $dir;
                    }

                    $files[$k]['size'] = $file['length'];
                }
            }
        } else {
            $files[0]['name'] = $decodedTorrent['info']['name'];
            $files[0]['size'] = $decodedTorrent['info']['length'];
        }

        return $files;
    }

    private static function collectFilesFromTree(array $tree, string $prefix, array &$files): void
    {
        foreach ($tree as $name => $node) {
            if ($name === '' && isset($node['length'])) {
                $files[] = [
                    'name' => rtrim($prefix, '/'),
                    'size' => $node['length'],
                ];
            } elseif (\is_array($node)) {
                self::collectFilesFromTree($node, $prefix.$name.'/', $files);
            }
        }
    }

    /**
     * Returns file and folder names from the torrent.
     *
     * @param  array<mixed>  $decodedTorrent
     * @return array<string>
     */
    public static function getFilenameArray(array $decodedTorrent): array
    {
        $filenames = [];

        $version = Bencode::get_torrent_version($decodedTorrent);

        if ($version >= 2 && isset($decodedTorrent['info']['file tree'])) {
            self::collectFilenamesFromTree($decodedTorrent['info']['file tree'], $filenames);
        } elseif (\array_key_exists('files', $decodedTorrent['info']) && (is_countable($decodedTorrent['info']['files']) ? \count($decodedTorrent['info']['files']) : 0)) {
            foreach ($decodedTorrent['info']['files'] as $file) {
                $count = is_countable($file['path']) ? \count($file['path']) : 0;

                for ($i = 0; $i < $count; $i++) {
                    if (!\in_array($file['path'][$i], $filenames)) {
                        $filenames[] = $file['path'][$i];
                    }
                }
            }
        } else {
            $filenames[] = $decodedTorrent['info']['name'];
        }

        return $filenames;
    }

    private static function collectFilenamesFromTree(array $tree, array &$filenames): void
    {
        foreach ($tree as $name => $node) {
            if ($name === '') {
                continue;
            }

            if (!\in_array($name, $filenames)) {
                $filenames[] = $name;
            }

            if (\is_array($node) && !isset($node['length'])) {
                self::collectFilenamesFromTree($node, $filenames);
            }
        }
    }

    /**
     * Returns the NFO.
     */
    public static function getNfo(?UploadedFile $inputFile): bool|string|null
    {
        if ($inputFile === null) {
            return null;
        }

        $fileName = uniqid('', true).'.nfo';
        $inputFile->move(getcwd().'/files/tmp/', $fileName);

        if (file_exists(getcwd().'/files/tmp/'.$fileName)) {
            $fileContent = file_get_contents(getcwd().'/files/tmp/'.$fileName);
            unlink(getcwd().'/files/tmp/'.$fileName);
        } else {
            $fileContent = null;
        }

        return $fileContent;
    }

    /**
     * Check if the filename is valid or not.
     */
    public static function isValidFilename(string $filename): bool
    {
        return !(
            \strlen($filename) > 255
            // nodes containing: `\`, `/`, `?`, `<`, `>`, `:`, `8`, `|`, and ascii characters from 0 through 31
            || preg_match('/[\\\\\\/?<>:*|"\x00-\x1f]/', $filename)
            // nodes only containing one or many: `.`; or only containing one or many `.`, ` `.
            || preg_match('/(^\\.+|[. ]+)$/', $filename)
            // Special windows filenames.
            || preg_match('/^(con|prn|aux|nul|com\d|lpt\d)(\\..*)?$/i', $filename)
            // BitComet padding files
            || preg_match('/^\.?____padding.*$/i', $filename)
            // BEP 47 torrent padding files that many clients aren't able to handle
            || str_starts_with($filename, '.pad')
        );
    }

    /**
     * Anonymize A Torrent Media Info.
     */
    public static function anonymizeMediainfo(string|Stringable|null $mediainfo): ?string
    {
        if ($mediainfo === null) {
            return null;
        }

        if ($mediainfo instanceof Stringable) {
            $mediainfo = $mediainfo->toString();
        }

        $completeNameI = strpos($mediainfo, 'Complete name');

        if ($completeNameI !== false) {
            $pathI = strpos($mediainfo, ': ', $completeNameI);

            if ($pathI !== false) {
                $pathI += 2;
                $endI = strpos($mediainfo, "\n", $pathI);
                $path = substr($mediainfo, $pathI, $endI - $pathI);
                $newPath = MediaInfo::stripPath($path);

                return substr_replace($mediainfo, $newPath, $pathI, \strlen($path));
            }
        }

        return $mediainfo;
    }

    /**
     * Parse Torrent Keywords.
     *
     * @return array<string>
     */
    public static function parseKeywords(string|Stringable $text): array
    {
        if ($text instanceof Stringable) {
            $text = $text->toString();
        }

        $keywords = array_filter(array_map('trim', explode(',', $text)));

        // unique keywords only (case insensitive)
        return array_values(array_intersect_key($keywords, array_unique(array_map('strtolower', $keywords))));
    }
}
