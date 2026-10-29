<?php

namespace ErnestDefoe\Herald;

final class Settings
{
    public const BATCH_SIZE = 'ernestdefoe-herald.batch_size';
    public const BATCH_DELAY = 'ernestdefoe-herald.batch_delay';
    public const REPLY_TO = 'ernestdefoe-herald.reply_to';

    /** Shared by every mailing. 0 or empty means no limit. */
    public const LIMIT_MINUTE = 'ernestdefoe-herald.limit_minute';
    public const LIMIT_HOUR = 'ernestdefoe-herald.limit_hour';
    public const LIMIT_DAY = 'ernestdefoe-herald.limit_day';

    /** The signup widget. Empty copy falls back to the translated defaults. */
    public const GUEST_SIGNUP = 'ernestdefoe-herald.guest_signup';
    public const WIDGET_TITLE = 'ernestdefoe-herald.widget_title';
    public const WIDGET_TEXT = 'ernestdefoe-herald.widget_text';
    public const WIDGET_BUTTON = 'ernestdefoe-herald.widget_button';

    /** Set once the two starter templates have been created. */
    public const STARTERS = 'ernestdefoe-herald.starters_installed';

    public const DEFAULT_BATCH_SIZE = 50;
    public const DEFAULT_BATCH_DELAY = 0;

    public const PERMISSION = 'ernestdefoe-herald.send';
}
