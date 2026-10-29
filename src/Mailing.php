<?php

namespace ErnestDefoe\Herald;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Formatter\Formatter;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property string $type editor | builder
 * @property array|null $blocks
 * @property string|null $preheader
 * @property array|null $frozen
 * @property bool $is_template
 * @property Carbon|null $archived_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $schedule_anchor
 * @property string|null $timezone
 * @property string|null $repeat_every
 * @property int|null $series_id
 * @property int $skipped_count
 * @property int $subscriber_cursor
 * @property int $max_subscriber_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $creator
 */
class Mailing extends AbstractModel
{
    public const DRAFT = 'draft';
    public const SENDING = 'sending';
    public const SENT = 'sent';
    public const CANCELLED = 'cancelled';
    public const SCHEDULED = 'scheduled';
    /** A scheduled issue whose only dynamic content had nothing in it. */
    public const SKIPPED = 'skipped';

    public const EDITOR = 'editor';
    public const BUILDER = 'builder';

    /** What a copy carries over: the words, the design and the audience. */
    public const CONTENT = ['subject', 'content', 'type', 'blocks', 'preheader', 'filters'];

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
        'blocks' => 'array',
        'frozen' => 'array',
        'is_template' => 'boolean',
        'archived_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'schedule_anchor' => 'datetime',
        'series_id' => 'integer',
        'skipped_count' => 'integer',
        'subscriber_cursor' => 'integer',
        'max_subscriber_id' => 'integer',
    ];

    protected $attributes = [
        'type' => self::EDITOR,
        'is_template' => false,
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSending(): bool
    {
        return $this->status === self::SENDING;
    }

    public function isBuilt(): bool
    {
        return $this->type === self::BUILDER;
    }

    /**
     * Whether there is anything to send: blocks for a builder mailing, a
     * message for an editor one.
     */
    public function hasBody(): bool
    {
        return $this->isBuilt() ? ! empty($this->blocks) : (bool) $this->content;
    }

    /**
     * A new draft with this one's content and audience — for Copy, Save as
     * template, starting from a template, and each issue of a series.
     */
    public function duplicate(array $overrides = []): self
    {
        $copy = new self();

        foreach (self::CONTENT as $field) {
            $copy->setAttribute($field, $this->getAttribute($field));
        }

        $copy->status = self::DRAFT;

        foreach ($overrides as $key => $value) {
            $copy->setAttribute($key, $value);
        }

        return $copy;
    }
}
