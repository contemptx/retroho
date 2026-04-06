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
 * @author     Roardom <roardom@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Http\Requests;

use App\Helpers\Bencode;
use App\Helpers\TorrentTools;
use App\Models\Category;
use App\Models\Scopes\ApprovedScope;
use App\Models\Torrent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Closure;
use Exception;

class StoreTorrentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<Closure(string, mixed, Closure(string): never): void|\Illuminate\Validation\Rules\ProhibitedIf|\Illuminate\Validation\ConditionalRules|\Illuminate\Validation\Rules\Unique|string>>
     */
    public function rules(Request $request): array
    {
        $category = Category::findOrFail($request->integer('category_id'));

        return [
            'torrent' => [
                'required',
                'file',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value->getClientOriginalExtension() !== 'torrent') {
                        $fail('The torrent file uploaded does not have a ".torrent" file extension (it has "'.$value->getClientOriginalExtension().'"). Did you upload the correct file?');
                    }

                    $decodedTorrent = TorrentTools::normalizeTorrent($value);

                    $version = Bencode::get_torrent_version($decodedTorrent);

                    if ($version >= 2) {
                        if (!isset($decodedTorrent['info']['file tree']) || !isset($decodedTorrent['info']['meta version'])) {
                            $fail('Invalid BitTorrent v2 torrent: missing required file tree or meta version.');
                        }

                        if (!isset($decodedTorrent['info']['piece length']) || ($decodedTorrent['info']['piece length'] & ($decodedTorrent['info']['piece length'] - 1)) !== 0 || $decodedTorrent['info']['piece length'] < 16384) {
                            $fail('BitTorrent v2 requires piece length to be a power of 2 and at least 16 KiB.');
                        }
                    }

                    try {
                        $meta = Bencode::get_meta($decodedTorrent);
                    } catch (Exception) {
                        $fail('You Must Provide A Valid Torrent File For Upload!');
                    }

                    /*foreach (TorrentTools::getFilenameArray($decodedTorrent) as $name) {
                        if (!TorrentTools::isValidFilename($name)) {
                            $fail('Invalid Filenames In Torrent Files!');
                        }
                    }*/

                    if ($version === 1 || $version === 3) {
                        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->where('info_hash', '=', Bencode::get_infohash($decodedTorrent))->first();

                        if ($torrent !== null) {
                            match ($torrent->status) {
                                Torrent::PENDING   => $fail('A torrent with the same info_hash has already been uploaded and is pending moderation.'),
                                Torrent::APPROVED  => $fail('A torrent with the same info_hash has already been uploaded and has been approved.'),
                                Torrent::REJECTED  => $fail('A torrent with the same info_hash has already been uploaded and has been rejected.'),
                                Torrent::POSTPONED => $fail('A torrent with the same info_hash has already been uploaded and is currently postponed.'),
                                default            => null,
                            };
                        }
                    }

                    if ($version >= 2) {
                        $torrentV2 = Torrent::withoutGlobalScope(ApprovedScope::class)->where('info_hash_v2', '=', Bencode::get_infohash_v2($decodedTorrent))->first();

                        if ($torrentV2 !== null) {
                            match ($torrentV2->status) {
                                Torrent::PENDING   => $fail('A torrent with the same v2 info_hash has already been uploaded and is pending moderation.'),
                                Torrent::APPROVED  => $fail('A torrent with the same v2 info_hash has already been uploaded and has been approved.'),
                                Torrent::REJECTED  => $fail('A torrent with the same v2 info_hash has already been uploaded and has been rejected.'),
                                Torrent::POSTPONED => $fail('A torrent with the same v2 info_hash has already been uploaded and is currently postponed.'),
                                default            => null,
                            };
                        }
                    }
                }
            ],
            'nfo' => [
                'nullable',
                'sometimes',
                'file',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value->getClientOriginalExtension() !== 'nfo') {
                        $fail('The NFO uploaded does not have a ".nfo" file extension (it has "'.$value->getClientOriginalExtension().'"). Did you upload the correct file?');
                    }
                },
            ],
            'name' => [
                'required',
                Rule::unique('torrents')->whereNull('deleted_at'),
                'max:255',
            ],
            'description' => [
                'required',
                'max:65535'
            ],
            'category_id' => [
                'required',
                'exists:categories,id',
            ],
            'type_id' => [
                'required',
                'exists:types,id',
            ],
            'anon' => [
                'required',
                'boolean',
            ],
            'free' => [
                'sometimes',
                'integer',
                'numeric',
                'between:0,100',
                Rule::when(!$request->user()->group->is_modo && !$request->user()->group->is_internal, 'prohibited'),
            ],
            'sku' => [
		'nullable',
		'sometimes',
		'string',
	    ],
	    'external_download_link' => [
		'nullable',
                'sometimes',
                'string',
            ],
        ];
    }
}
