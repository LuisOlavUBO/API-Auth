<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One login, failed attempt, block or rejected client. Written by App\Services\AuthenticationEvents;
 * API-IAM only reads them. Never holds passwords, secrets or tokens.
 */
#[Fillable(['occurred_at', 'type', 'method', 'user_id', 'email', 'client_id', 'ip_address', 'user_agent', 'reason'])]
class AuthenticationEvent extends Model
{
    use MassPrunable;

    /**
     * How many days the events are kept (model:prune runs daily, see routes/console.php).
     */
    public const RETENTION_DAYS = 180;

    public $timestamps = false;

    /**
     * The events older than the retention.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('occurred_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
