<?php

$captureFile = getenv('WEBHOOK_CAPTURE_FILE');

if ($captureFile !== false && $captureFile !== '') {
   file_put_contents(
      $captureFile,
      json_encode([
         'method' => $_SERVER['REQUEST_METHOD'] ?? '',
         'uri'    => $_SERVER['REQUEST_URI'] ?? '',
         'headers' => function_exists('getallheaders') ? getallheaders() : [],
         'body'   => file_get_contents('php://input'),
      ])
   );
}

http_response_code((int)($_GET['status'] ?? 200));
echo 'ok';
