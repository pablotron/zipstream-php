<?php
declare(strict_types = 1);

namespace Pablotron\ZipStream\Tests;

use PHPUnit\Framework\TestCase;
use \Pablotron\ZipStream\ZipStream;
use \Pablotron\ZipStream\FileWriter;

class BaseTestCase extends TestCase {
  protected function open_archive(string $path) {
    # open archive, check for error
    $zip = new \ZipArchive();
    if ($zip->open($path) !== true) {
      throw new Exception("ZipArchive#open() failed: $dst_name");
    }

    # return archive
    return $zip;
  }

  protected function with_temp_file(callable $cb) : void {
    # build path to temp file
    $path = tempnam('/tmp', 'zipstream-test');
    try {
      # pass to test
      $cb($path);
    } finally {
      if (file_exists($path)) {
        # remove temp file if it exists
        unlink($path);
      }
    }
  }

  protected function with_temp_zip(callable $zip_cb, callable $test_cb = null) {
    $this->with_temp_file(function(string $dst_path) use ($zip_cb, $test_cb) {
      # create zip, pass to zip callback
      ZipStream::send($dst_path, $zip_cb, [
        'output' => new FileWriter(),
      ]);

      if ($test_cb) {
        # pass to test callback
        $test_cb($dst_path);
      }
    });
  }
};
