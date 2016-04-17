<?php

/**
 * Backblaze B2 file storage engine. This engine scales very nicely but is
 * relatively high-latency since data has to be pulled out of B2.
 *
 * @task internal Internals
 */
final class PhabricatorB2FileStorageEngine
  extends PhabricatorFileStorageEngine {

/* -(  Engine Metadata  )---------------------------------------------------- */

  /**
   * This engine identifies as `backblaze-b2`.
   */
  public function getEngineIdentifier() {
    return 'backblaze-b2';
  }

  /**
   * Mark the engine as high priority. Just below S3, since it's not upstream
   * and thus less stable.
   */
  public function getEnginePriority() {
    return 99;
  }

  /**
   * Allow file writes
   */
  public function canWriteFiles() {
    $bucket_id = PhabricatorEnv::getEnvConfig('storage.b2.bucket-id');
    $bucket_name = PhabricatorEnv::getEnvConfig('storage.b2.bucket-name');
    $account_id = PhabricatorEnv::getEnvConfig('backblaze-b2.account-id');
    $app_key = PhabricatorEnv::getEnvConfig('backblaze-b2.application-key');

    return (strlen($bucket_id) &&
            strlen($bucket_name) &&
            strlen($account_id) &&
            strlen($app_key));
  }


/* -(  Managing File Data  )------------------------------------------------- */

  /**
   * Writes file data into Amazon S3.
   */
  public function writeFile($data, array $params) {
    $b2 = $this->newB2API();
    $name = self::generateFileName();

    // Now, actually perform the write, and profile it
    AphrontWriteGuard::willWrite();
    $profiler = PhutilServiceProfiler::getInstance();

    // Perform the write
    $call_id = $this->startProfiledCall($profiler, 'uploadFile');
    $file_id = $b2->uploadFile($name, $data);
    $this->stopProfiledCall($profiler, $call_id);

    return $file_id;
  }

  /**
   * Load a stored blob from Amazon S3.
   */
  public function readFile($handle) {
    $b2 = $this->newB2API();

    $profiler = PhutilServiceProfiler::getInstance();

    // Perform the read
    $call_id = $this->startProfiledCall($profiler, 'downloadFile');
    $result = $b2->downloadFile($handle);
    $this->stopProfiledCall($profiler, $call_id);

    return $result;
  }

  /**
   * Delete a blob from Amazon S3.
   */
  public function deleteFile($handle) {
    $b2 = $this->newB2API();

    AphrontWriteGuard::willWrite();
    $profiler = PhutilServiceProfiler::getInstance();

    // Perform the delete
    $call_id = $this->startProfiledCall($profiler, 'deleteFileByName');
    $b2->deleteFile($handle);
    $this->stopProfiledCall($profiler, $call_id);
  }


/* -(  Internals  )---------------------------------------------------------- */

  /**
   * Generate a random filename to upload into the bucket.
   *
   * @task internal
   */
  private static function generateFileName() {
    // Generate a random name for this file. We add some directories to it
    // (e.g. 'abcdef123456' becomes 'ab/cd/ef123456') to make large numbers of
    // files more browsable with web/debugging tools like the S3 administration
    // tool.
    $seed = Filesystem::readRandomCharacters(20);
    $parts = array();
    $parts[] = 'phabricator';

    $instance_name = PhabricatorEnv::getEnvConfig('cluster.instance');
    if (strlen($instance_name)) {
      $parts[] = $instance_name;
    }

    $parts[] = substr($seed, 0, 2);
    $parts[] = substr($seed, 2, 2);
    $parts[] = substr($seed, 4);

    $name = implode('/', $parts);
    return $name;
  }

  /**
   * Retrieve the B2 bucket name and ID.
   *
   * @task internal
   */
  private function getBucket() {
    $bucket_id = PhabricatorEnv::getEnvConfig('storage.b2.bucket-id');
    $bucket_name = PhabricatorEnv::getEnvConfig('storage.b2.bucket-name');

    if (!$bucket_id) {
      throw new PhabricatorFileStorageConfigurationException(
        pht(
          "No '%s' specified!",
          'storage.b2.bucket-id'));
    }

    if (!$bucket_name) {
      throw new PhabricatorFileStorageConfigurationException(
        pht(
          "No '%s' specified!",
          'storage.b2.bucket-name'));
    }

    return array($bucket_id, $bucket_name);
  }

  /**
   * Create a new Backblaze B2 API object.
   *
   * @task internal
   */
  private function newB2API() {
    $libroot = dirname(phutil_get_library_root('libphutil-backblaze'));
    require_once $libroot.'/externals/BackblazeB2.php';

    list($bucket_id, $bucket_name) = $this->getBucket();
    $account_id = PhabricatorEnv::getEnvConfig('backblaze-b2.account-id');
    $app_key = PhabricatorEnv::getEnvConfig('backblaze-b2.application-key');

    return id(new BackblazeB2())
      ->setApplicationKey($app_key)
      ->setAccountId($account_id)
      ->setBucketName($bucket_name)
      ->setBucketId($bucket_id);
  }

  /**
   * Simple utility to make profiling calls more brief
   *
   * @task internal
   */
  private function startProfiledCall($profiler, $method) {
    $call_id = $profiler->beginServiceCall(
      array(
        'type'   => 'b2',
        'method' => $method,
      ));
    return $call_id;
  }

  private function stopProfiledCall($profiler, $id) {
    $profiler->endServiceCall($id, array());
  }
}

// Local Variables:
// fill-column: 80
// indent-tabs-mode: nil
// c-basic-offset: 2
// buffer-file-coding-system: utf-8-unix
// End:
