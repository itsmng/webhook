<?php

namespace tests\units;

use GlpiPlugin\Webhook\Config;
use GlpiPlugin\Webhook\Notification;
use GlpiPlugin\Webhook\NotificationEventWebhook;
use GlpiPlugin\Webhook\Template;
use GlpiPlugin\Webhook\UserWebhook;
use GlpiPlugin\Webhook\Webhook;

class PluginWebhookNotificationEventWebhook extends \DbTestCase
{
   public function testWebhookTargetsUseUserIds()
   {
      $data = [];

      $this->string(NotificationEventWebhook::getTargetField($data))->isIdenticalTo('users_id');
      $this->array($data)->isIdenticalTo(['users_id' => null]);
   }

   public function testPrivateFollowupDoesNotEnterWebhookPipeline()
   {
      Config::setValues(['notifications_webhook' => '1']);
      $target = $this->newMockInstance(\NotificationTarget::class);

      $this->raiseWebhookEvent(
         $target,
         'add_followup',
         ['followup_id' => 42, 'is_private' => 1]
      );

      $this->mock($target)->call('getEntity')->never();
   }

   public function testPublicFollowupWithoutNotificationRecipientDoesNotEnterWebhookPipeline()
   {
      Config::setValues(['notifications_webhook' => '1']);
      $target = $this->newMockInstance(\NotificationTarget::class);

      $this->raiseWebhookEvent(
         $target,
         'add_followup',
         ['followup_id' => 43, 'is_private' => 0]
      );

      $this->mock($target)->call('getEntity')->never();
   }

   public function testRecipientWithoutMatchingUserWebhookDoesNotEnterWebhookPipeline()
   {
      Config::setValues(['notifications_webhook' => '1']);
      $webhookId = $this->addWebhook('Unassigned recipient webhook', 0, 1);
      $this->addRule($webhookId);

      $target = $this->newMockInstance(\NotificationTarget::class);
      $this->calling($target)->getEntity = 0;
      $data = $this->prepareRecipient($target, $webhookId, false);

      $this->raiseWebhookEvent(
         $target,
         'new',
         ['is_private' => 0],
         $data,
         true
      );

      $this->mock($target)->call('getForTemplate')->never();
   }

   public function testDebugEventDoesNotEnterWebhookPipeline()
   {
      Config::setValues(['notifications_webhook' => '1']);
      $target = $this->newMockInstance(\NotificationTarget::class);

      $this->raiseWebhookEvent(
         $target,
         'add_followup',
         ['followup_id' => 44, 'is_private' => 0],
         [],
         false,
         'New followup'
      );

      $this->mock($target)->call('getEntity')->never();
   }

   public function testDisabledWebhookModeDoesNotEnterWebhookPipeline()
   {
      Config::setValues(['notifications_webhook' => '0']);
      $target = $this->newMockInstance(\NotificationTarget::class);

      $this->raiseWebhookEvent(
         $target,
         'add_followup',
         ['followup_id' => 45, 'is_private' => 0]
      );

      $this->mock($target)->call('getEntity')->never();
   }

   public function testRuleIsDispatchedOnlyOnceWhenCoreRaisesMultipleWebhookNotifications()
   {
      Config::setValues(['notifications_webhook' => '1']);
      $webhookId = $this->addWebhook('Deduplicated webhook', 0, 1);
      $this->addRule($webhookId);

      $target = $this->newMockInstance(\NotificationTarget::class);
      $this->calling($target)->getEntity = 0;
      $this->calling($target)->getForTemplate = [];
      $data = $this->prepareRecipient($target, $webhookId);

      $processed = [];
      $options = ['is_private' => 0, 'processed' => &$processed];
      for ($i = 0; $i < 2; $i++) {
         $this->raiseWebhookEvent($target, 'new', $options, $data);
      }

      $this->mock($target)->call('getForTemplate')->once();
   }

   public function testWebhookMustBeAvailableToTheEventEntity()
   {
      Config::setValues(['notifications_webhook' => '1']);
      $child1 = (int)getItemByTypeName('Entity', '_test_child_1', true);
      $child2 = (int)getItemByTypeName('Entity', '_test_child_2', true);
      $webhookId = $this->addWebhook('Entity-scoped webhook', $child1, 0);
      $this->addRule($webhookId);

      $target = $this->newMockInstance(\NotificationTarget::class);
      $this->calling($target)->getEntity = $child2;
      $data = $this->prepareRecipient($target, $webhookId);

      $this->raiseWebhookEvent($target, 'new', ['is_private' => 0], $data);

      $this->mock($target)->call('getForTemplate')->never();
   }

   public function testRecursiveWebhookFromParentIsAvailableToChildEntity()
   {
      Config::setValues(['notifications_webhook' => '1']);
      $parent = (int)getItemByTypeName('Entity', '_test_root_entity', true);
      $child = (int)getItemByTypeName('Entity', '_test_child_1', true);
      $webhookId = $this->addWebhook('Recursive parent webhook', $parent, 1);
      $this->addRule($webhookId, $parent);

      $target = $this->newMockInstance(\NotificationTarget::class);
      $this->calling($target)->getEntity = $child;
      $this->calling($target)->getForTemplate = [];
      $data = $this->prepareRecipient($target, $webhookId);

      $this->raiseWebhookEvent($target, 'new', ['is_private' => 0], $data);

      $this->mock($target)->call('getForTemplate')->once();
   }

   public function testTemplateRenderingUsesAnAnonymousPublicContext()
   {
      Config::setValues(['notifications_webhook' => '1']);
      $webhookId = $this->addWebhook('Public rendering webhook', 0, 1);
      $this->addRule($webhookId);

      $renderOptions = null;
      $target = $this->newMockInstance(\NotificationTarget::class);
      $this->calling($target)->getEntity = 0;
      $this->calling($target)->getForTemplate = function ($event, $options) use (&$renderOptions) {
         $renderOptions = $options;
         return [];
      };
      $data = $this->prepareRecipient($target, $webhookId);

      $this->raiseWebhookEvent(
         $target,
         'new',
         [
            'is_private' => 0,
            'additionnaloption' => [
               'usertype' => \NotificationTarget::GLPI_USER,
               'show_private' => 1,
            ],
         ],
         $data
      );

      $this->integer($renderOptions['additionnaloption']['usertype'])
         ->isIdenticalTo(\NotificationTarget::ANONYMOUS_USER);
      $this->integer($renderOptions['additionnaloption']['show_private'])->isIdenticalTo(0);
   }

   private function raiseWebhookEvent(
      $target,
      string $event,
      array $options = [],
      array $data = [],
      bool $notifyMe = false,
      string $label = ''
   ): void {
      NotificationEventWebhook::raise(
         $event,
         new \Ticket(),
         $options,
         $label,
         $data,
         $target,
         new \NotificationTemplate(),
         $notifyMe
      );
   }

   private function addWebhook(string $name, int $entitiesId, int $isRecursive): int
   {
      global $DB;

      $webhook = new Webhook();
      $id = $webhook->add([
         'name' => $name,
         'url' => 'http://127.0.0.1:1/' . strtolower(str_replace(' ', '-', $name)),
         'http_method' => 'POST',
         'headers' => '{}',
         'is_active' => 1,
         'timeout' => 1,
         'verify_ssl' => 0,
         'entities_id' => $entitiesId,
         'is_recursive' => $isRecursive,
      ]);

      $this->integer((int)$id)->isGreaterThan(0);
      $this->boolean($DB->update(
         Webhook::getTable(),
         ['url' => 'not a url'],
         ['id' => $id]
      ))->isTrue();

      return (int)$id;
   }

   private function prepareRecipient($target, int $webhookId, bool $attachWebhook = true): array
   {
      global $DB;

      $userId = (int)getItemByTypeName('User', TU_USER, true);
      $target->setEvent(NotificationEventWebhook::class);
      $this->calling($target)->validateSendTo = true;
      $target->addToRecipientsList(['users_id' => $userId]);

      if ($attachWebhook) {
         $relation = new UserWebhook();
         $this->integer((int)$relation->add([
            'users_id' => $userId,
            'plugin_webhook_webhooks_id' => $webhookId,
            'is_active' => 1,
         ]))->isGreaterThan(0);
      }

      $notificationId = 1000000 + $webhookId;
      $this->boolean($DB->insert('glpi_notificationtargets', [
         'notifications_id' => $notificationId,
         'items_id' => 0,
         'type' => 0,
      ]))->isTrue();

      return ['id' => $notificationId];
   }

   private function addRule(int $webhookId, int $entitiesId = 0): void
   {
      $template = new Template();
      $this->boolean($template->getFromDBByCrit(['itemtype' => 'Ticket']))->isTrue();

      $notification = new Notification();
      $id = $notification->add([
         'name' => 'Rule for new',
         'plugin_webhook_webhooks_id' => $webhookId,
         'itemtype' => 'Ticket',
         'event' => 'new',
         'entities_id' => $entitiesId,
         'is_recursive' => 1,
         'is_active' => 1,
         'plugin_webhook_templates_id' => (int)$template->fields['id'],
      ]);

      $this->integer((int)$id)->isGreaterThan(0);
   }
}
