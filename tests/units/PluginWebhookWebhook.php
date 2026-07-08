<?php

namespace tests\units;

use GlpiPlugin\Webhook\Config;
use GlpiPlugin\Webhook\Webhook;

class PluginWebhookWebhook extends \DbTestCase
{
   public function testInputValidationNormalizesUrlMethodHeadersAndDefaults()
   {
      Config::setValues([
         'webhook_default_timeout' => '23',
         'webhook_verify_ssl' => '0',
      ]);

      $webhook = new Webhook();
      $id = $webhook->add([
         'name' => 'Model webhook',
         'url' => 'https://example.com/hook',
         'http_method' => 'delete',
         'header_keys' => ['Authorization', '', 'X-Custom'],
         'header_values' => ['Bearer token', 'ignored', 'custom'],
         'is_active' => 1,
      ]);

      $this->integer((int)$id)->isGreaterThan(0);
      $this->boolean($webhook->getFromDB($id))->isTrue();
      $this->string($webhook->fields['url'])->isIdenticalTo('https://example.com/hook');
      $this->string($webhook->fields['http_method'])->isIdenticalTo('POST');
      $this->integer((int)$webhook->fields['timeout'])->isIdenticalTo(23);
      $this->integer((int)$webhook->fields['verify_ssl'])->isIdenticalTo(0);

      $headers = json_decode($webhook->fields['headers'], true);
      $this->array($headers)->isIdenticalTo([
         'Authorization' => 'Bearer token',
         'X-Custom' => 'custom',
      ]);
   }

   public function testRejectsInvalidUrl()
   {
      $webhook = new Webhook();
      $id = $webhook->add([
         'name' => 'Invalid webhook',
         'url' => 'ftp://example.com/hook',
      ]);

      $this->boolean($id)->isFalse();
      $this->hasSessionMessages(ERROR, [__('Invalid URL', 'webhook')]);
   }
}
