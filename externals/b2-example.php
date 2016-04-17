<?php

require 'BackblazeB2.php';

// -----------------------------
// Parse configuration

$json = json_decode(file_get_contents('b2-example.json'), true);

$app_key = $json['application-key'];
$account_id = $json['account-id'];
$bucket_id = $json['bucket-id'];
$bucket_name = $json['bucket-name'];

$b2 = new BackblazeB2();
$b2->setApplicationKey($app_key);
$b2->setAccountId($account_id);
$b2->setBucketName($bucket_name);
$b2->setBucketId($bucket_id);

// -----------------------------
// Run test

$data = 'hello world';

// 1: Upload data
$id = $b2->uploadFile('testing.txt', $data);
echo "OK: uploaded file.\n";

// 2: Download data
$download = $b2->downloadFile($id);
echo "OK: downloaded file.\n";

// 3: Verify data
if ($download !== $data) {
  echo "FAILURE: downloaded data didn't match!\n";
} else {
  echo "OK: data uploaded/downloaded fine.\n";
}

// 4: Delete data
$b2->deleteFile($id);
echo "OK: deleted file.\n";

// -----------------------------
// El fin

// Local Variables:
// fill-column: 80
// indent-tabs-mode: nil
// c-basic-offset: 2
// buffer-file-coding-system: utf-8-unix
// End:
