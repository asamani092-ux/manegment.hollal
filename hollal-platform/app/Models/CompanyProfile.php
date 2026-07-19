<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 05-B3 — company profile used on quote and contract PDFs (including the
 * tax number / الرقم الضريبي). Managed inside the partnerships module.
 */
class CompanyProfile extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name', 'tax_number', 'commercial_register', 'address',
        'phone', 'email', 'iban', 'logo_path',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['name' => 'مؤسسة حلّل', 'tax_number' => '300000000000003'],
        );
    }
}
