<?php

/**
 * Simple, easy-to-use Backblaze B2 API, directly utilizing cURL.
 */
final class BackblazeB2 {

  private $bucketId;
  private $bucketName;
  private $accountId;
  private $applicationKey;

/* -(  Accessors  )---------------------------------------------------------- */

  public function setBucketId($bucket_id) {
    $this->bucketId = $bucket_id;
    return $this;
  }

  public function getBucketId() {
    return $this->bucketId;
  }

  public function setBucketName($bucket_name) {
    $this->bucketName = $bucket_name;
    return $this;
  }

  public function getBucketName() {
    return $this->bucketName;
  }

  public function setAccountId($id) {
    $this->accountId = $id;
    return $this;
  }

  public function getAccountId() {
    return $this->accountId;
  }

  public function setApplicationKey($key) {
    $this->applicationKey = $key;
    return $this;
  }

  public function getApplicationKey() {
    return $this->applicationKey;
  }

/* -(  Primary API  )-------------------------------------------------------- */

  /**
   * Upload a file to a B2 bucket, with a given name, and the specified data.
   * Returns the ID of the file that was created by the upload.
   *
   * @param $name Name of the file you wish to create.
   * @param $data File data you want to upload.
   * @return string ID of the file you created.
   */
  public function uploadFile($name, $data) {
    list($upload_url, $token) = $this->getUploadUrl();

    // Execute
    $hash = sha1($data);
    $response = self::execCurl(
      $upload_url, 'POST', array(
        'Content-Type: application/octet-stream',
        'Authorization: '.$token,
        'X-Bz-File-Name: '.$name,
        'X-Bz-Content-Sha1: '.$hash,
      ), $data);

    // Parse and get back a sane JSON value.
    $json = self::isValidJson($response);

    // Now, check the JSON map has every key-value pair we expect.
    self::jsonHasKeys(
      $json, array(
        'fileId',
        'fileName',
      ));

    // And, just to be sure: make sure we get the right file back.
    self::jsonKeyIs($json, 'fileName', $name);

    // Return the file ID.
    return $json['fileId'];
  }

  /**
   * Download the contents of a file from a B2 bucket. The $handle must be
   * the ID of the file in the bucket you wish to retrieve.
   *
   * @param $handle ID of the file you wish to download.
   */
  public function downloadFile($handle) {
    list($api_url, $token, $download_url) = $this->authorizeAccount();

    $full_url = $download_url;
    $full_url .= '/b2api/v1/b2_download_file_by_id?fileId=';
    $full_url .= $handle;

    // Execute
    $response = self::execCurl(
      $full_url, 'GET', array(
        'Authorization: '.$token,
      ));

    return $response;
  }

  /**
   * Delete a file from a B2 bucket. The $handle parameter must be
   * the ID of the file in the bucket you wish to delete.
   *
   * @param $handle ID of the file that you wish to delete.
   */
  public function deleteFile($handle) {
    $file_name = $this->getFileName($handle);

    list($api_url, $token, $download_url) = $this->authorizeAccount();
    $full_url = $api_url.'/b2api/v1/b2_delete_file_version';

    // Execute
    $response = self::execCurl(
      $full_url, 'POST', array(
        'Authorization: '.$token,
      ),
      json_encode(array(
        'fileId' => $handle,
        'fileName' => $file_name,
      )));

    // Parse and get back a sane JSON value.
    $json = self::isValidJson($response);

    // Now, check the JSON map has every key-value pair we expect.
    self::jsonHasKeys(
      $json, array(
        'fileId',
        'fileName',
      ));

    // And, just to be sure: make sure we get the right file back.
    self::jsonKeyIs($json, 'fileId', $handle);
    self::jsonKeyIs($json, 'fileName', $file_name);
  }

/* -(  Utilities  )---------------------------------------------------------- */

  /**
   * Get the file ID for a particular file
   *
   * @task internal
   */
  private function getFileName($handle) {
    list($api_url, $token, $download_url) = $this->authorizeAccount();
    $full_url = $api_url.'/b2api/v1/b2_get_file_info';

    // Execute
    $response = self::execCurl(
      $full_url, 'POST', array(
        'Authorization: '.$token,
      ),
      json_encode(array(
        'fileId' => $handle,
      )));

    // Parse and get back a sane JSON value.
    $json = self::isValidJson($response);

    // Now, check the JSON map has every key-value pair we expect.
    self::jsonHasKeys(
      $json, array(
        'fileId',
        'fileName',
      ));

    // And, just to be sure: make sure we get the right bucket ID back.
    self::jsonKeyIs($json, 'fileId', $handle);

    // Done: return the filename
    return $json['fileName'];
  }

  /**
   * Execute a request against the b2_get_upload_url API, and return
   *
   * @task internal
   */
  private function getUploadUrl() {
    list($api_url, $token, $download_url) = $this->authorizeAccount();
    $full_url = $api_url.'/b2api/v1/b2_get_upload_url';

    // Execute
    $response = self::execCurl(
      $full_url, 'POST', array(
        'Authorization: '.$token,
      ),
      json_encode(array(
        'bucketId' => $this->getBucketId(),
      )));

    // Parse and get back a sane JSON value.
    $json = self::isValidJson($response);

    // Now, check the JSON map has every key-value pair we expect.
    self::jsonHasKeys(
      $json, array(
        'bucketId',
        'authorizationToken',
        'uploadUrl',
      ));

    // And, just to be sure: make sure we get the right bucket ID back.
    self::jsonKeyIs($json, 'bucketId', $this->getBucketId());

    // Done: return the needed values
    return array(
      $json['uploadUrl'],
      $json['authorizationToken'],
    );
  }

  /**
   * Execute a request against the b2_authorize_account API, returning
   * the new API endpoint, an authorization token, and a download URL.
   *
   * @task internal
   */
  private function authorizeAccount() {
    $url = 'https://api.backblaze.com/b2api/v1/b2_authorize_account';

    // Execute
    $response = self::execCurl(
      $url, 'GET', array(
        'Accept: application/json',
        'Authorization: Basic '.$this->getEncodedCredentials(),
      ));

    // Parse and get back a sane JSON value.
    $json = self::isValidJson($response);

    // Check the JSON map has every key-value pair we expect.
    self::jsonHasKeys(
      $json, array(
        'apiUrl',
        'authorizationToken',
        'accountId',
        'downloadUrl',
      ));

    // And, just to be sure: make sure we get the right account ID back.
    self::jsonKeyIs($json, 'accountId', $this->getAccountId());

    // Done: return the needed values
    return array(
      $json['apiUrl'],
      $json['authorizationToken'],
      $json['downloadUrl'],
    );
  }

  /**
   * Encode B2 credentials in a form necessary for making the
   * `b2_authorize_account` API call.
   *
   * @task internal
   */
  private function getEncodedCredentials() {
    $str = $this->getAccountId().':'.$this->getApplicationKey();
    return base64_encode($str);
  }

/* -(  Misc helpers  )------------------------------------------------------- */

  /**
   * Ensures the input is valid JSON after decoding. Returns an associative
   * array, representing the input JSON.
   *
   * @task internal
   */
  private static function isValidJson($val) {
    $json = json_decode($val, true);
    if ($json === null || $json === true || $json === false) {
      throw new Exception("Couldn't decode JSON response!");
    }

    return $json;
  }

  /**
   * Ensure a json associative array has every key specified in the second
   * argument.
   *
   * @task internal
   */
  private static function jsonHasKeys($json, $keys) {
    foreach ($keys as $key) {
      if (!isset($json[$key])) {
        throw new Exception(
          'Malformed JSON response! Did not contain expected field '.$key);
      }
    }
  }

  /**
   * Ensure a json map has a key with a specific value.
   *
   * @task internal
   */
  private static function jsonKeyIs($json, $key, $is) {
    if ($json[$key] !== $is) {
      throw new Exception(
        'Malformed JSON response! Expected the key '.$key.' to have the '.
        'value "'.$is.'"');
    }
  }

/* -(  cURL helpers  )------------------------------------------------------- */

  /**
   * Execute a cURL request, abstracted to support the common needs for
   * the Backblaze API.
   *
   * @task internal
   */
  private static function execCurl($full_url, $type,
                                   $headers, $post_data = null) {
    $curl_request = self::getCurlRequestType($type);

    if ($post_data !== null) {
      if ($type !== 'POST') {
        throw new Exception('Cannot post field data with non-POST request!');
      }
    }

    $session = curl_init($full_url);
    curl_setopt($session, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($session, $curl_request, true);
    if ($post_data !== null) {
      curl_setopt($session, CURLOPT_POSTFIELDS, $post_data);
    }

    // Do it
    $server_output = curl_exec($session);
    curl_close($session);

    if ($server_output === false) {
      throw new Exception("Couldn't make cURL request!");
    }

    return $server_output;
  }

  /**
   * Get a curl_setopt option for a given kind of HTTP request, expressed as a
   * string. Currently only accepts 'GET' and 'POST'.
   *
   * @task internal
   */
  private static function getCurlRequestType($type) {
    if ($type === 'GET') {
      return CURLOPT_HTTPGET;
    } else if ($type === 'POST') {
      return CURLOPT_POST;
    } else {
      throw Exception('Unknown Curl request type!');
    }
  }
}

// Local Variables:
// fill-column: 80
// indent-tabs-mode: nil
// c-basic-offset: 2
// buffer-file-coding-system: utf-8-unix
// End:
