<?php

namespace App\Models;

use Database\Factories\AiProviderCredentialFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProviderCredential extends Model
{
    /** @use HasFactory<AiProviderCredentialFactory> */
    use HasFactory;

    use HasUuids;

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
        'provider',
        'api_key',
        'base_url',
        'extra',
        'is_active',
        'key_hint',
        'last_validated_at',
    ];

    protected $hidden = [
        'api_key',
        'base_url',
        'extra',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'base_url' => 'encrypted',
            'extra' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_validated_at' => 'datetime',
        ];
    }
}
