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

use App\Models\Category;
use App\Models\Scopes\ApprovedScope;
use App\Models\Torrent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateTorrentRequest extends FormRequest
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
     * @return array<string, array<\Illuminate\Validation\ConditionalRules|\Illuminate\Validation\Rules\Unique|string>|string>
     */
    public function rules(Request $request): array
    {
        $category = Category::findOrFail($request->integer('category_id'));

        /** @var string $torrentId */
        $torrentId = $request->route('id');

        return [
            'name' => [
                'required',
                Rule::unique('torrents')->whereNot('id', $torrentId)->whereNull('deleted_at'),
                'max:255',
            ],
            'description' => [
                'required',
                'max:2097152'
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
                Rule::when(Torrent::withoutGlobalScope(ApprovedScope::class)->find($request->route('id'))->user_id !== $request->user()->id && !$request->user()->group->is_modo, 'exclude'),
            ],
            'free' => [
                'sometimes',
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
