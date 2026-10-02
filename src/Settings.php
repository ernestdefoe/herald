<?php

namespace ErnestDefoe\Herald;

final class Settings
{
    public const BATCH_SIZE = 'ernestdefoe-herald.batch_size';
    public const BATCH_DELAY = 'ernestdefoe-herald.batch_delay';
    public const REPLY_TO = 'ernestdefoe-herald.reply_to';

    public const DEFAULT_BATCH_SIZE = 50;
    public const DEFAULT_BATCH_DELAY = 0;

    public const PERMISSION = 'ernestdefoe-herald.send';
}
