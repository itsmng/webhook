<?php

namespace tests\units;

use GlpiPlugin\Webhook\Notification;
use GlpiPlugin\Webhook\Template;
use GlpiPlugin\Webhook\Webhook;

class PluginWebhookNotification extends \DbTestCase
{
   public function testRulesAreFilteredByEventTypeEntityAndActiveFlag()
   {
      $webhookId = $this->addWebhook('Notification webhook');
      $templateId = $this->getTemplateId('Ticket');

      $notification = new Notification();
      $activeId = $notification->add([
         'name' => 'Active rule',
         'plugin_webhook_webhooks_id' => $webhookId,
         'itemtype' => 'Ticket',
         'event' => 'new',
         'entities_id' => 0,
         'is_recursive' => 1,
         'is_active' => 1,
         'plugin_webhook_templates_id' => $templateId,
      ]);
      $this->integer((int)$activeId)->isGreaterThan(0);

      $notification->add([
         'name' => 'Inactive rule',
         'plugin_webhook_webhooks_id' => $webhookId,
         'itemtype' => 'Ticket',
         'event' => 'new',
         'entities_id' => 0,
         'is_recursive' => 1,
         'is_active' => 0,
         'plugin_webhook_templates_id' => $templateId,
      ]);
      $notification->add([
         'name' => 'Other event rule',
         'plugin_webhook_webhooks_id' => $webhookId,
         'itemtype' => 'Ticket',
         'event' => 'update',
         'entities_id' => 0,
         'is_recursive' => 1,
         'is_active' => 1,
         'plugin_webhook_templates_id' => $templateId,
      ]);

      $rules = Notification::getWebhookNotifications('new', 'Ticket', 0);

      $this->array($rules)->hasSize(1);
      $this->integer((int)array_values($rules)[0]['id'])->isIdenticalTo((int)$activeId);
   }

   private function addWebhook(string $name): int
   {
      $webhook = new Webhook();
      $id = $webhook->add([
         'name' => $name,
         'url' => 'https://example.com/' . strtolower(str_replace(' ', '-', $name)),
         'http_method' => 'POST',
         'headers' => '{}',
         'is_active' => 1,
         'timeout' => 5,
         'verify_ssl' => 1,
         'entities_id' => 0,
         'is_recursive' => 1,
      ]);

      $this->integer((int)$id)->isGreaterThan(0);

      return (int)$id;
   }

   private function getTemplateId(string $itemtype): int
   {
      $template = new Template();
      $this->boolean($template->getFromDBByCrit(['itemtype' => $itemtype]))->isTrue();

      return (int)$template->fields['id'];
   }
}
