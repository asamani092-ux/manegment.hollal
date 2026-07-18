<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class MailSetting extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'host', 'port', 'encryption', 'username', 'password',
        'from_address', 'from_name', 'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'port' => 'integer',
        ];
    }

    /**
     * The single settings row, created empty on first access.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * Whether SMTP has the minimum configuration to attempt a send.
     */
    public function isConfigured(): bool
    {
        return filled($this->host) && filled($this->port) && filled($this->from_address);
    }

    /**
     * Apply this configuration to the runtime SMTP mailer so both interactive
     * and queued mail use the stored credentials.
     */
    public function applyToConfig(): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $this->host);
        Config::set('mail.mailers.smtp.port', $this->port);
        Config::set('mail.mailers.smtp.scheme', $this->encryption === 'tls' ? 'smtp' : ($this->encryption === 'ssl' ? 'smtps' : null));
        Config::set('mail.mailers.smtp.encryption', $this->encryption);
        Config::set('mail.mailers.smtp.username', $this->username);
        Config::set('mail.mailers.smtp.password', $this->password);
        Config::set('mail.from.address', $this->from_address);
        Config::set('mail.from.name', $this->from_name ?: config('mail.from.name'));
    }
}
