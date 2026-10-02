<?php

namespace ErnestDefoe\Herald;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Formatter\Formatter;
use Flarum\User\User;
use TypeError;

/**
 * One bulk email.
 *
 * The body is stored the way a post's is — formatter XML in `content`, turned
 * back into editable source for the editor — so Markdown, FoF Rich Text and
 * Scribe all work without Herald knowing which one the forum runs.
 *
 * @property int $id
 * @property string $subject
 * @property string|null $content
 * @property array|null $filters
 * @property string $status
 * @property int $recipient_total
 * @property int $sent_count
 * @property int $failed_count
 * @property int $cursor
 * @property int $max_user_id
 * @property Carbon|null $locked_until
 * @property Carbon|null $last_batch_at
 * @property int|null $created_by
 * @property int|null $sent_by
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Mailing extends AbstractModel
{
    public const DRAFT = 'draft';
    public const SENDING = 'sending';
    public const SENT = 'sent';
    public const CANCELLED = 'cancelled';

    protected $table = 'herald_mailings';

    public $timestamps = true;

    protected $casts = [
        'filters' => 'array',
        'recipient_total' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'cursor' => 'integer',
        'max_user_id' => 'integer',
        'locked_until' => 'datetime',
        'last_batch_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static Formatter $formatter;

    public static function setFormatter(Formatter $formatter): void
    {
        static::$formatter = $formatter;
    }

    public static function getFormatter(): Formatter
    {
        return static::$formatter;
    }

    /**
     * The body as the editor should see it.
     */
    public function source(): string
    {
        return (string) static::$formatter->unparse($this->content, $this);
    }

    public function setSource(string $source, User $actor): void
    {
        $this->content = trim($source) === '' ? null : static::$formatter->parse($source, $this, $actor);
    }

    /**
     * The body as HTML, with its quick tags still in place.
     */
    public function renderBody(): string
    {
        if (! $this->content) {
            return '';
        }

        try {
            return static::$formatter->render($this->content, $this);
        } catch (TypeError $e) {
            /*
             * 🚨 Flarum's formatter is two halves that must agree: a SERIALIZED
             * renderer in the cache and the generated class file it is an
             * instance of, in storage/formatter. A cache:clear racing a request
             * can delete the file and leave the cache entry, and from then on
             * every render throws "__PHP_Incomplete_Class returned" — nothing
             * self-heals, because the entry is cached forever.
             *
             * A mailing mid-send would mark every remaining member as failed.
             * Forgetting the entry makes the next render rebuild both halves.
             */
            if (! str_contains($e->getMessage(), '__PHP_Incomplete_Class')) {
                throw $e;
            }

            static::$formatter->flush();

            return static::$formatter->render($this->content, $this);
        }
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSending(): bool
    {
        return $this->status === self::SENDING;
    }
}
