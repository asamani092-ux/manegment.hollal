<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tokenized signature request for the platform-wide /sign portal.
 * Time: O(1) | Space: O(1)
 */
class SignatureRequest extends Model
{
    public const STATUS_PENDING = 'معلق';

    public const STATUS_SIGNED = 'موقّع';

    public const STATUS_EXPIRED = 'منته';

    public const STATUS_CANCELLED = 'ملغى';

    public const TYPE_MEETING_GUEST = 'meeting_guest';

    public const TYPE_PARTNERSHIP_CONTRACT = 'partnership_contract';

    /** @var list<string> */
    protected $fillable = [
        'token',
        'document_type',
        'document_id',
        'status',
        'signer_name',
        'signer_position',
        'signature_image_path',
        'signature_hash',
        'expires_at',
        'signed_at',
        'meta',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'signed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function isUsable(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
