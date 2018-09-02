<?php
declare(strict_types = 1);

namespace Pablotron\ZipStream\Tests;

use \PHPUnit\Framework\TestCase;
use \Pablotron\ZipStream\ZipStream;

final class AddFileTest extends BaseTestCase {
  public function testCreateFile() : void {
    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('hello.txt', 'hello!');
    }, function(string $path) {
      $zip = $this->open_archive($path);

      $this->assertEquals(
        'hello!',
        $zip->getFromName('hello.txt')
      );
    });
  }

  public function testCreateFileWithComment() : void {
    $comment = 'test comment';
    $this->with_temp_zip(function(ZipStream &$zip) use ($comment) {
      $zip->add_file('hello.txt', 'hello!', [
        'comment' => $comment,
      ]);
    }, function(string $path) use ($comment) {
      $zip = $this->open_archive($path);

      $this->assertEquals(
        $comment,
        $zip->getCommentName('hello.txt')
      );
    });
  }

  public function testCreateFileWithUnknownMethod() : void {
    $this->expectException(\Pablotron\ZipStream\UnknownMethodError::class);

    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('hello.txt', 'hello!', [
        'method'  => -20,
      ]);
    });
  }

  public function testCreateFileTimestamp() : void {
    # get timezone offset
    # $ofs = \DateTimeZone::getOffset(\DateTime::getTimezone());
    $ofs = 4 * 3600; # hard-coded to EDT for now

    # get time from 2 hours ago (round to even number of seconds)
    $time = ((time() - (2 * 3600)) >> 1) << 1;

    $this->with_temp_zip(function(ZipStream &$zip) use ($time) {
      $zip->add_file('hello.txt', 'hello!', [
        'time' => $time,
      ]);
    }, function($zip_path) use ($time, $ofs) {
      $zip = $this->open_archive($zip_path);
      $st = $zip->statName('hello.txt');
      $this->assertEquals($time, $st['mtime'] - $ofs);
    });
  }
};
