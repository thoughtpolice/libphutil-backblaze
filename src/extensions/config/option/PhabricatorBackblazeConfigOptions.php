<?php

/**
 * Adds a section on the 'Config' application for configuring
 * Backblaze B2 storage.
 */
final class PhabricatorBackblazeConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Backblaze Storage');
  }

  public function getDescription() {
    return pht('Configure integration with Backblaze B2 Storage.');
  }

  public function getIcon() {
    return 'fa-hdd-o';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      /* -- B2 storage options. -- */
      $this->newOption('backblaze-b2.account-id', 'string', null)
        ->setLocked(true)
        ->setHidden(true)
        ->setDescription(pht('Account ID for Backblaze B2 Storage.')),
      $this->newOption('backblaze-b2.application-key', 'string', null)
        ->setLocked(true)
        ->setHidden(true)
        ->setDescription(pht('Application key for B2 storage.')),
      $this->newOption('storage.b2.bucket-name', 'string', null)
        ->setLocked(true)
        ->setHidden(true)
        ->setSummary(pht('Bucket name for file storage.'))
        ->setDescription(
          pht('Set this to a valid B2 Bucket ID to store files in.')),
      $this->newOption('storage.b2.bucket-id', 'string', null)
        ->setLocked(true)
        ->setHidden(true)
        ->setSummary(pht('Bucket id for file storage.'))
        ->setDescription(
          pht('Set this to a valid B2 Bucket Name to store the files in. '.
              'Note that this ID must be the exact bucket ID corresponding'.
              'to the bucket named in `storage.b2.bucket-name`.')),
    );
  }
}

// Local Variables:
// fill-column: 80
// indent-tabs-mode: nil
// c-basic-offset: 2
// buffer-file-coding-system: utf-8-unix
// End:
