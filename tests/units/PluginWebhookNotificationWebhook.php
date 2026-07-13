<?php

namespace tests\units;

use GlpiPlugin\Webhook\Config;
use GlpiPlugin\Webhook\NotificationWebhook;
use GlpiPlugin\Webhook\TemplateTranslation;
use GlpiPlugin\Webhook\Webhook;

class PluginWebhookNotificationWebhook extends \DbTestCase
{
   private $serverProcess = null;

   public function afterTestMethod($method)
   {
      $this->stopServer();
      parent::afterTestMethod($method);
   }

   public function testCheckAcceptsOnlyValidUrls()
   {
      $this->boolean(NotificationWebhook::check('https://example.com/hook'))->isTrue();
      $this->boolean(NotificationWebhook::check('not a url'))->isFalse();
   }

   public function testSendNotificationPostsPayloadHeadersAndMethodToWebhook()
   {
      $captureFile = tempnam(sys_get_temp_dir(), 'webhook-capture-');
      $url = $this->startServer($captureFile);

      $sent = (new NotificationWebhook())->sendNotification([
         'recipient' => $url . '/receiver',
         'sender' => 'PATCH',
         'sendername' => json_encode([
            'timeout' => 3,
            'verify_ssl' => false,
         ]),
         'headers' => json_encode([
            'X-Webhook-Test' => 'header value',
         ]),
         'body_text' => '{"message":"hello"}',
      ]);

      $this->integer($sent)->isIdenticalTo(1);

      $capture = $this->readCapture($captureFile);
      $this->string($capture['method'])->isIdenticalTo('PATCH');
      $this->string($capture['body'])->isIdenticalTo('{"message":"hello"}');
      $this->array($capture['headers'])->hasKey('X-Webhook-Test');
      $this->string($capture['headers']['X-Webhook-Test'])->isIdenticalTo('header value');
      $this->array($capture['headers'])->hasKey('Content-Type');
      $this->string($capture['headers']['Content-Type'])->contains('application/json');

      @unlink($captureFile);
   }

   public function testSendNotificationPostsRenderedRichTextAsValidJson()
   {
      $captureFile = tempnam(sys_get_temp_dir(), 'webhook-capture-');
      $url = $this->startServer($captureFile);
      $richText = '<p class="ticket-body">The user said "restart it".</p>'
         . "\n<p>Path: C:\\Temp\\log.txt & details: caf\xC3\xA9</p>";
      $payload = TemplateTranslation::processPayloadTemplate(
         '{"description":"##ticket.description##"}',
         ['##ticket.description##' => $richText]
      );

      $sent = (new NotificationWebhook())->sendNotification([
         'recipient' => $url . '/receiver',
         'sender' => 'POST',
         'body_text' => $payload,
      ]);

      $this->integer($sent)->isIdenticalTo(1);

      $capture = $this->readCapture($captureFile);
      $this->string($capture['body'])->isIdenticalTo($payload);
      $this->string($capture['body'])->contains('class=\\"ticket-body\\"');

      $decoded = json_decode($capture['body'], true);
      $this->integer(json_last_error())->isIdenticalTo(JSON_ERROR_NONE);
      $this->string($decoded['description'])->isIdenticalTo($richText);

      @unlink($captureFile);
   }

   public function testSendNotificationAcceptsLegacySerializedHeadersAndDefaultConfig()
   {
      Config::setValues([
         'webhook_default_timeout' => '3',
         'webhook_verify_ssl' => '1',
      ]);

      $captureFile = tempnam(sys_get_temp_dir(), 'webhook-capture-');
      $url = $this->startServer($captureFile);

      $sent = (new NotificationWebhook())->sendNotification([
         'recipient' => $url . '/receiver',
         'sender' => 'POST',
         'headers' => serialize(['X-Legacy' => 'yes']),
         'body_text' => '{"legacy":true}',
      ]);

      $this->integer($sent)->isIdenticalTo(1);

      $capture = $this->readCapture($captureFile);
      $this->string($capture['method'])->isIdenticalTo('POST');
      $this->string($capture['body'])->isIdenticalTo('{"legacy":true}');
      $this->string($capture['headers']['X-Legacy'])->isIdenticalTo('yes');

      @unlink($captureFile);
   }

   public function testSendWebhookReturnsZeroForInvalidUrl()
   {
      $this->integer(NotificationWebhook::sendWebhook('not a url', '{}'))->isIdenticalTo(0);
   }

   public function testTestNotificationUsesFirstActiveWebhook()
   {
      $captureFile = tempnam(sys_get_temp_dir(), 'webhook-capture-');
      $url = $this->startServer($captureFile);

      $webhook = new Webhook();
      $this->integer((int)$webhook->add([
         'name' => 'Test active webhook',
         'url' => $url . '/test',
         'http_method' => 'POST',
         'headers' => json_encode(['X-Test-Notification' => '1']),
         'is_active' => 1,
         'timeout' => 3,
         'verify_ssl' => 0,
         'entities_id' => 0,
         'is_recursive' => 1,
      ]))->isGreaterThan(0);

      $this->integer(NotificationWebhook::testNotification())->isIdenticalTo(1);

      $capture = $this->readCapture($captureFile);
      $this->string($capture['method'])->isIdenticalTo('POST');
      $this->string($capture['body'])->isIdenticalTo('{"message":"Webhook test"}');
      $this->string($capture['headers']['X-Test-Notification'])->isIdenticalTo('1');

      @unlink($captureFile);
   }

   private function startServer(string $captureFile): string
   {
      $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
      $this->resource($socket)->isNotNull();
      $address = stream_socket_get_name($socket, false);
      fclose($socket);

      $port = (int)substr(strrchr($address, ':'), 1);
      $router = dirname(__DIR__) . '/fixtures/webhook_receiver.php';
      $command = sprintf(
         'WEBHOOK_CAPTURE_FILE=%s php -S 127.0.0.1:%d %s',
         escapeshellarg($captureFile),
         $port,
         escapeshellarg($router)
      );

      $this->serverProcess = proc_open(
         $command,
         [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
         ],
         $pipes
      );
      $this->resource($this->serverProcess)->isNotNull();
      fclose($pipes[0]);

      $this->waitForServer($port);

      return 'http://127.0.0.1:' . $port;
   }

   private function waitForServer(int $port): void
   {
      for ($i = 0; $i < 50; $i++) {
         $connection = @fsockopen('127.0.0.1', $port);
         if (is_resource($connection)) {
            fclose($connection);
            return;
         }
         usleep(100000);
      }

      $this->fail('Webhook receiver server did not start');
   }

   private function readCapture(string $captureFile): array
   {
      for ($i = 0; $i < 20; $i++) {
         $contents = @file_get_contents($captureFile);
         if (is_string($contents) && $contents !== '') {
            $decoded = json_decode($contents, true);
            if (is_array($decoded)) {
               return $decoded;
            }
         }
         usleep(100000);
      }

      $this->fail('Webhook receiver did not capture a request');
   }

   private function stopServer(): void
   {
      if (is_resource($this->serverProcess)) {
         proc_terminate($this->serverProcess);
         proc_close($this->serverProcess);
      }
      $this->serverProcess = null;
   }
}
