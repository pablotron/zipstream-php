<?php
declare(strict_types = 1);

namespace Pablotron\ZipStream\Tests;

use \PHPUnit\Framework\TestCase;
use \Pablotron\ZipStream\ZipStream;

final class LargeFileTest extends BaseTestCase {
  public function testCreateLargeFile() : void {
    # build 4M string
    $chunk_size = (1 << 22);
    $num_chunks = 1025;

    # calculate expected size
    $expected_size = $chunk_size * $num_chunks;

    $this->with_temp_zip(function(ZipStream &$zip) use ($chunk_size, $num_chunks) {
      $zip->add('hello.txt', function($e) use ($chunk_size, $num_chunks) {
        # build chunk
        $data = str_repeat('x', $chunk_size);

        # repeatedly write chunk
        foreach (range(0, $num_chunks - 1) as $i) {
          $e->write($data);
        }
      });
    }, function($zip_path) use ($expected_size) {
      $zip = $this->open_archive($zip_path);
      $st = $zip->statName('hello.txt');

      $this->assertEquals($expected_size, $st['size']);
    });
  }
};
