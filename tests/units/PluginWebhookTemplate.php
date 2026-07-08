<?php

namespace tests\units;

use GlpiPlugin\Webhook\Template;
use GlpiPlugin\Webhook\TemplateTranslation;

class PluginWebhookTemplate extends \DbTestCase
{
   public function testDefaultTemplatesAreSeededWithValidDefaultTranslations()
   {
      global $DB;

      foreach (['Ticket', 'Change', 'Problem'] as $itemtype) {
         $this->integer(countElementsInTable(Template::getTable(), ['itemtype' => $itemtype]))
            ->isGreaterThanOrEqualTo(1);
      }

      $translations = $DB->request([
         'SELECT' => ['payload_template'],
         'FROM' => TemplateTranslation::getTable(),
         'WHERE' => ['language' => ''],
      ]);

      $this->integer(count($translations))->isGreaterThanOrEqualTo(3);
      foreach ($translations as $translation) {
         $this->boolean(TemplateTranslation::isValidJson($translation['payload_template']))->isTrue();
      }
   }

   public function testDefaultPayloadsAreValidJsonForSupportedItemtypes()
   {
      foreach (['Ticket', 'Change', 'Problem'] as $itemtype) {
         $payload = Template::getDefaultPayloadForItemtype($itemtype);
         $decoded = json_decode($payload, true);

         $this->integer(json_last_error())->isIdenticalTo(JSON_ERROR_NONE);
         $this->string($decoded['description'])->isIdenticalTo('##' . strtolower($itemtype) . '.description##');
         $this->array($decoded)->hasKeys(['event', 'id', 'title', 'url', 'authors']);
      }
   }
}
