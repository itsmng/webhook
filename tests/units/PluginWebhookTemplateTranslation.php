<?php

namespace tests\units;

use GlpiPlugin\Webhook\TemplateTranslation;

class PluginWebhookTemplateTranslation extends \GLPITestCase
{
   public function testProcessPayloadTemplateEscapesSubstitutedJsonStringValues()
   {
      $payload = TemplateTranslation::processPayloadTemplate(
         '{"description":"##ticket.description##"}',
         [
            '##ticket.description##' => "A \"quoted\" description\nwith\ttabs and C:\\Temp\\file.txt",
         ]
      );

      $this->variable(json_decode($payload))->isNotNull();
      $this->integer(json_last_error())->isIdenticalTo(JSON_ERROR_NONE);

      $decoded = json_decode($payload, true);
      $this->string($decoded['description'])
         ->isIdenticalTo("A \"quoted\" description\nwith\ttabs and C:\\Temp\\file.txt");
   }

   public function testProcessPayloadTemplateEscapesNestedValuesAndObjectKeys()
   {
      $payload = TemplateTranslation::processPayloadTemplate(
         '{"##ticket.key##":{"items":["##ticket.description##"],"enabled":true,"count":2}}',
         [
            '##ticket.key##' => 'ticket "description"',
            '##ticket.description##' => "line 1\r\nline \"2\"",
         ]
      );

      $this->variable(json_decode($payload))->isNotNull();
      $this->integer(json_last_error())->isIdenticalTo(JSON_ERROR_NONE);

      $decoded = json_decode($payload, true);
      $this->array($decoded)->hasKey('ticket "description"');
      $this->string($decoded['ticket "description"']['items'][0])
         ->isIdenticalTo("line 1\r\nline \"2\"");
      $this->boolean($decoded['ticket "description"']['enabled'])->isTrue();
      $this->integer($decoded['ticket "description"']['count'])->isIdenticalTo(2);
   }

   public function testProcessPayloadTemplateKeepsLegacyProcessingForInvalidJsonTemplates()
   {
      $payload = TemplateTranslation::processPayloadTemplate(
         'description=##ticket.description##',
         [
            '##ticket.description##' => 'plain text',
         ]
      );

      $this->string($payload)->isIdenticalTo('description=plain text');
   }
}
