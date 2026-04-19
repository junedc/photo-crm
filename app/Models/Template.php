<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'subject',
        'preheader',
        'headline',
        'html_body',
        'button_text',
        'button_url',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
