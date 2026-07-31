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

   public function testPrivateFollowupUsesAuthorizedRecipientVisibility()
   {
      Config::setValues(['notifications_webhook' => '1']);
      $webhookId = $this->addWebhook('Private followup webhook', 0, 1);
      $this->addRule($webhookId, 0, 'add_followup');

      $renderOptions = null;
      $target = $this->newMockInstance(\NotificationTarget::class);
      $this->calling($target)->getEntity = 0;
      $this->calling($target)->getForTemplate = function ($event, $options) use (&$renderOptions) {
         $renderOptions = $options;
         return [];
      };
      $data = $this->prepareRecipient($target, $webhookId, true, ['show_private' => 1]);

      $this->raiseWebhookEvent(
         $target,
         'add_followup',
         ['followup_id' => 42, 'is_private' => 1],
         $data
      );

      $this->mock($target)->call('getForTemplate')->once();
      $this->integer($renderOptions['additionnaloption']['usertype'])
         ->isIdenticalTo(\NotificationTarget::ANONYMOUS_USER);
      $this->integer($renderOptions['additionnaloption']['show_private'])->isIdenticalTo(1);
   }

   public function testPrivateFollowupWithoutAuthorizedRecipientDoesNotEnterWebhookPipeline()
   {
      Config::setValues(['notifications_webhook' => '1']);
      $webhookId = $this->addWebhook('Unauthorized private followup webhook', 0, 1);
      $this->addRule($webhookId, 0, 'add_followup');

      $target = $this->newMockInstance(\NotificationTarget::class);
      $this->calling($target)->getEntity = 0;
      $data = $this->prepareRecipient($target, $webhookId, true, ['show_private' => 0]);
      $this->calling($target)->validateSendTo = false;

      $this->raiseWebhookEvent(
         $target,
         'add_followup',
         ['followup_id' => 42, 'is_private' => 1],
         $data
      );

      $this->mock($target)->call('getForTemplate')->never();
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

   public function testSelfServiceWatcherCanTriggerAssignedWebhook()
   {
      global $DB;

      $this->login();
      $_SESSION += [
         'INCOMING' => \CommonITILObject::INCOMING,
         'ASSIGNED' => \CommonITILObject::ASSIGNED,
         'PLANNED' => \CommonITILObject::PLANNED,
         'SOLVED' => \CommonITILObject::SOLVED,
         'CLOSED' => \CommonITILObject::CLOSED,
      ];
      Config::setValues(['notifications_webhook' => '1']);
      $userId = (int)getItemByTypeName('User', 'post-only', true);
      $profileId = (int)getItemByTypeName('Profile', 'Self-Service', true);
      $this->integer($userId)->isGreaterThan(0);
      $profile = new \Profile();
      $this->boolean($profile->getFromDB($profileId))->isTrue();
      $this->string($profile->fields['interface'])->isIdenticalTo('helpdesk');
      $this->integer(countElementsInTable('glpi_profiles_users', [
         'users_id' => $userId,
      ]))->isIdenticalTo(1);
      $this->integer(countElementsInTable('glpi_profiles_users', [
         'users_id' => $userId,
         'profiles_id' => $profileId,
      ]))->isIdenticalTo(1);

      $ticket = new \Ticket();
      $ticketId = (int)$ticket->add([
         'name' => 'Webhook notification for self-service watcher',
         'content' => 'Self-service watcher webhook regression',
         'entities_id' => 0,
      ]);
      $this->integer($ticketId)->isGreaterThan(0);
      $ticketUser = new \Ticket_User();
      $this->integer((int)$ticketUser->add([
         'tickets_id' => $ticketId,
         'users_id' => $userId,
         'type' => \CommonITILActor::OBSERVER,
         'use_notification' => 1,
      ]))->isGreaterThan(0);
      $this->boolean($DB->update(
         'glpi_tickets',
         ['content' => 'Self-service watcher webhook regression'],
         ['id' => $ticketId]
      ))->isTrue();
      $this->boolean($ticket->getFromDB($ticketId))->isTrue();
      $this->string($ticket->getField('content'))->isNotEmpty();
      $this->integer(countElementsInTable('glpi_tickets_users', [
         'tickets_id' => $ticketId,
         'users_id' => $userId,
         'type' => \CommonITILActor::OBSERVER,
      ]))->isIdenticalTo(1);

      $webhookId = $this->addWebhook('Self-service watcher webhook', 0, 1);
      $ruleId = $this->addRule($webhookId, 0, 'add_followup');
      $rule = new Notification();
      $this->boolean($rule->getFromDB($ruleId))->isTrue();
      $this->boolean($DB->update(
         'glpi_plugin_webhook_template_translations',
         ['payload_template' => '{"id":"##ticket.id##"}'],
         [
            'plugin_webhook_templates_id' => (int)$rule->fields['plugin_webhook_templates_id'],
            'language' => '',
         ]
      ))->isTrue();
      $relation = new UserWebhook();
      $this->integer((int)$relation->add([
         'users_id' => $userId,
         'plugin_webhook_webhooks_id' => $webhookId,
         'is_active' => 1,
      ]))->isGreaterThan(0);

      $notificationId = 2000000 + $webhookId;
      $this->boolean($DB->insert('glpi_notificationtargets', [
         'notifications_id' => $notificationId,
         'items_id' => \Notification::OBSERVER,
         'type' => \Notification::USER_TYPE,
      ]))->isTrue();

      $eventOptions = ['followup_id' => 123, 'is_private' => 0];
      $target = \NotificationTarget::getInstance($ticket, 'add_followup', $eventOptions);
      $this->object($target)->isInstanceOf(\NotificationTargetTicket::class);
      $target->setMode('webhook');
      $target->setEvent(NotificationEventWebhook::class);
      $target->addForTarget([
         'notifications_id' => $notificationId,
         'items_id' => \Notification::OBSERVER,
         'type' => \Notification::USER_TYPE,
      ]);
      $recipients = $target->getTargets();
      $this->array($recipients)->hasKey($userId);
      $this->integer((int)$recipients[$userId]['users_id'])->isIdenticalTo($userId);
      $emitterId = (int)getItemByTypeName('User', TU_USER, true);
      $this->integer($emitterId)->isNotEqualTo($userId);
      $this->boolean(
         $target->validateSendTo('add_followup', $recipients[$userId], false, $emitterId)
      )->isTrue();
      $this->boolean(
         $target->validateSendTo('add_followup', $recipients[$userId], false, $userId)
      )->isFalse();
      $this->array(UserWebhook::getWebhooksForUser($userId))->contains($webhookId);
      $target->clearAddressesList();

      $processed = [];
      NotificationEventWebhook::raise(
         'add_followup',
         $ticket,
         $eventOptions + ['processed' => &$processed],
         '',
         ['id' => $notificationId],
         $target,
         new \NotificationTemplate(),
         false,
         $emitterId
      );

      $this->boolean(isset($processed['rules'][$ruleId]))->isTrue();
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

   public function testTemplateRenderingUsesAnonymousContextWithRecipientPrivateVisibility()
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
      $data = $this->prepareRecipient($target, $webhookId, true, ['show_private' => 1]);

      $this->raiseWebhookEvent(
         $target,
         'new',
         [
            'is_private' => 0,
            'additionnaloption' => [
               'usertype' => \NotificationTarget::GLPI_USER,
               'show_private' => 0,
            ],
         ],
         $data
      );

      $this->integer($renderOptions['additionnaloption']['usertype'])
         ->isIdenticalTo(\NotificationTarget::ANONYMOUS_USER);
      $this->integer($renderOptions['additionnaloption']['show_private'])->isIdenticalTo(1);
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

   private function prepareRecipient(
      $target,
      int $webhookId,
      bool $attachWebhook = true,
      array $additionnalOptions = []
   ): array
   {
      global $DB;

      $userId = (int)getItemByTypeName('User', TU_USER, true);
      $target->setEvent(NotificationEventWebhook::class);
      $this->calling($target)->validateSendTo = true;
      $this->calling($target)->addAdditionnalUserInfo = $additionnalOptions;
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

   private function addRule(int $webhookId, int $entitiesId = 0, string $event = 'new'): int
   {
      $template = new Template();
      $this->boolean($template->getFromDBByCrit(['itemtype' => 'Ticket']))->isTrue();

      $notification = new Notification();
      $id = $notification->add([
         'name' => 'Rule for ' . $event,
         'plugin_webhook_webhooks_id' => $webhookId,
         'itemtype' => 'Ticket',
         'event' => $event,
         'entities_id' => $entitiesId,
         'is_recursive' => 1,
         'is_active' => 1,
         'plugin_webhook_templates_id' => (int)$template->fields['id'],
      ]);

      $this->integer((int)$id)->isGreaterThan(0);

      return (int)$id;
   }
}
