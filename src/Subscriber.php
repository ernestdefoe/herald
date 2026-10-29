<?php

namespace ErnestDefoe\Herald;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * A guest who signed up for the newsletter from the widget, and confirmed it
 * from their inbox. Unconfirmed rows are never mailed and are purged after a
 * week.
 *
 * @property int $id
 * @property string $email
 * @property string|null $token_hash
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $unsubscribed_at
 * @property string|null $locale
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Subscriber extends AbstractModel
{
    /** How long a confirmation link works. */
    public const CONFIRM_HOURS = 48;

    /** How long an address nobody confirmed is kept. */
    public const PURGE_DAYS = 7;

    protected $table = 'herald_subscribers';

    public $timestamps = true;

    protected $casts = [
        'token_expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    /**
     * Who may be sent a newsletter: confirmed, still subscribed, and not the
     * address of a member — a member's consent is their own switch, and
     * matching both would send them two copies.
     *
     * @param Builder<self> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotNull('herald_subscribers.confirmed_at')
            ->whereNull('herald_subscribers.unsubscribed_at')
            ->whereNotExists(function ($sub) {
                $grammar = $sub->getGrammar();
                $sub->selectRaw('1')
                    ->from('users')
                    ->whereRaw('LOWER('.$grammar->wrap('users.email').') = '.$grammar->wrap('herald_subscribers.email'));
            });
    }

    /**
     * A fresh confirmation link's secret. Only its hash is stored.
     */
    public function issueToken(): string
    {
        $token = bin2hex(random_bytes(24));

        $this->token_hash = hash('sha256', $token);
        $this->token_expires_at = Carbon::now()->addHours(self::CONFIRM_HOURS);

        return $token;
    }

    public function tokenMatches(string $token): bool
    {
        return $this->token_hash !== null
            && $this->token_expires_at?->isFuture()
            && hash_equals($this->token_hash, hash('sha256', $token));
    }

    public function isActive(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }
}
