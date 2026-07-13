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

   public function testProcessPayloadTemplateHandlesClientLastFollowupTemplate()
   {
      $template = <<<'JSON'
{
    "event": "##ticket.action##",
    "ticketId": "##ticket.id##",
    "category": "##ticket.category##",
    "requesttype": "##ticket.requesttype##",
    "description": "##FOREACH last followups## ##followup.description## ##ENDFOREACHfollowups##",
    "author": "##FOREACH last followups## ##followup.author## ##ENDFOREACHfollowups##",
    "isprivate": "##FOREACH last followups## ##followup.isprivate## ##ENDFOREACHfollowups##",
    "observergroups": "##ticket.observergroups##"
}
JSON;
      $latestDescription = "Latest \"quoted\" followup\nPath: C:\\Temp\\ticket.txt";
      $payload = TemplateTranslation::processPayloadTemplate($template, [
         '##ticket.action##' => 'New followup',
         '##ticket.id##' => '0564233',
         '##ticket.category##' => 'Applications',
         '##ticket.requesttype##' => 'Incident',
         '##ticket.observergroups##' => 'Operations',
         'followups' => [
            [
               '##followup.description##' => $latestDescription,
               '##followup.author##' => 'Operator "A"',
               '##followup.isprivate##' => 'No',
            ],
            [
               '##followup.description##' => 'Older followup',
               '##followup.author##' => 'Operator B',
               '##followup.isprivate##' => 'Yes',
            ],
         ],
      ]);

      $decoded = json_decode($payload, true);
      $this->integer(json_last_error())->isIdenticalTo(JSON_ERROR_NONE);
      $this->string($decoded['event'])->isIdenticalTo('New followup');
      $this->string($decoded['ticketId'])->isIdenticalTo('0564233');
      $this->string($decoded['description'])->isIdenticalTo(' ' . $latestDescription . ' ');
      $this->string($decoded['author'])->isIdenticalTo(' Operator "A" ');
      $this->string($decoded['isprivate'])->isIdenticalTo(' No ');
      $this->string($decoded['observergroups'])->isIdenticalTo('Operations');
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
