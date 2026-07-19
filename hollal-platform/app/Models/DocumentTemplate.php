<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 07-B1 — the reusable template library (نماذج جاهزة).
 */
class DocumentTemplate extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['title', 'category', 'path', 'description', 'uploaded_by'];
}
