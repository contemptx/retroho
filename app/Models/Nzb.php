<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nzb extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'path',
        'password',
	'description',
        'metadata',
        'active',
        'title',
        'description',
        'category_id',
        'type_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Belongs To A Category.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Category, $this>
     */
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Belongs To A Type.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Type, $this>
     */
    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Type::class);
    }

}
