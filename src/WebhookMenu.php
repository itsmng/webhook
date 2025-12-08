<?php

namespace GlpiPlugin\Webhook;

use CommonGLPI;
use Plugin;
use Session;

class WebhookMenu extends CommonGLPI {

    public static function getTypeName($nb = 0): string {
        return __('Webhooks', 'webhook');
    }

    public static function getIcon(): string {
        return 'fas fa-link';
    }

    public static function getMenuContent(): array|false {
        $menu = [];

        if (Webhook::canView() || Config::canView()) {

            $menu['title'] = __('Webhooks', 'webhook');
            $menu['page']  = Plugin::getWebDir('webhook') . '/front/webhook.center.php';
            $menu['icon']  = self::getIcon();

            // Webhooks submenu
            $menu['options']['webhook']['title']           = Webhook::getTypeName(Session::getPluralNumber());
            $menu['options']['webhook']['page']            = Webhook::getSearchURL(false);
            $menu['options']['webhook']['icon']            = 'fas fa-link';
            $menu['options']['webhook']['links']['add']    = Webhook::getFormURL(false);
            $menu['options']['webhook']['links']['search'] = Webhook::getSearchURL(false);

            // Templates submenu
            $menu['options']['template']['title']           = Template::getTypeName(Session::getPluralNumber());
            $menu['options']['template']['page']            = Template::getSearchURL(false);
            $menu['options']['template']['icon']            = 'fas fa-file-code';
            $menu['options']['template']['links']['add']    = Template::getFormURL(false);
            $menu['options']['template']['links']['search'] = Template::getSearchURL(false);

            // Notification rules submenu
            if (Notification::canView()) {
                $menu['options']['notification']['title']           = Notification::getTypeName(Session::getPluralNumber());
                $menu['options']['notification']['page']            = Notification::getSearchURL(false);
                $menu['options']['notification']['icon']            = 'fas fa-bell';
                $menu['options']['notification']['links']['add']    = Notification::getFormURL(false);
                $menu['options']['notification']['links']['search'] = Notification::getSearchURL(false);
            }

            // Config submenu
            if (Config::canView()) {
                $menu['options']['config']['title'] = __('Configuration');
                $menu['options']['config']['page']  = Config::getFormURL(false);
                $menu['options']['config']['icon']  = 'fas fa-cog';
            }

            return $menu;
        }

        return false;
    }
}
