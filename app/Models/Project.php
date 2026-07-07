<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasUuids;

    public $incrementing = true;
    protected $primaryKey = 'id';
    protected $fillable = [
        'name',
        'description'
    ];
}
