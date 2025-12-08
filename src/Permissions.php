<?php

namespace GlpiPlugin\Webhook;

use Session;

trait Permissions {
    protected static function canConfig(): bool {
        return Session::haveRight('plugin_webhook_config', UPDATE);
    }

    protected static function canUse(): bool {
        return Session::haveRight('plugin_webhook_use', READ) || self::canConfig();
    }

    protected static function canNotif(): bool {
        return Session::haveRight('plugin_webhook_notification', CREATE) || self::canConfig();
    }
}
