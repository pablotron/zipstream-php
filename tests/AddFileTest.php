<?php
declare(strict_types = 1);

namespace Pablotron\ZipStream\Tests;

use \PHPUnit\Framework\TestCase;
use \Pablotron\ZipStream\ZipStream;

final class AddFileTest extends BaseTestCase {
  public function testAddFile() : void {
    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('hello.txt', 'hello!');
    }, function(string $zip_path) {
      $zip = $this->open_archive($zip_path);

      $this->assertEquals(
        'hello!',
        $zip->getFromName('hello.txt')
      );
    });
  }

  public function testAddFileFromPath() : void {
    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file_from_path('test.php', __FILE__);
    }, function(string $zip_path) {
      $zip = $this->open_archive($zip_path);

      $this->assertEquals(
        sha1(file_get_contents(__FILE__)),
        sha1($zip->getFromName('test.php'))
      );
    });
  }

  public function testAddStream() : void {
    $this->with_temp_zip(function(ZipStream &$zip) {
      $fh = fopen(__FILE__, 'rb');
      $zip->add_stream('test.php', $fh);
      fclose($fh);
    }, function(string $zip_path) {
      $zip = $this->open_archive($zip_path);

      $this->assertEquals(
        sha1(file_get_contents(__FILE__)),
        sha1($zip->getFromName('test.php'))
      );
    });
  }

  public function testAdd() : void {
    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add('test.php', function(&$e) {
        $e->write(file_get_contents(__FILE__));
      });
    }, function(string $zip_path) {
      $zip = $this->open_archive($zip_path);

      $this->assertEquals(
        sha1(file_get_contents(__FILE__)),
        sha1($zip->getFromName('test.php'))
      );
    });
  }

  public function testAddFileWithComment() : void {
    $comment = 'test comment';
    $this->with_temp_zip(function(ZipStream &$zip) use ($comment) {
      $zip->add_file('hello.txt', 'hello!', [
        'comment' => $comment,
      ]);
    }, function(string $zip_path) use ($comment) {
      $zip = $this->open_archive($zip_path);

      $this->assertEquals(
        $comment,
        $zip->getCommentName('hello.txt')
      );
    });
  }

  public function testAddFileWithUnknownMethod() : void {
    $this->expectException(\Pablotron\ZipStream\UnknownMethodError::class);

    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('hello.txt', 'hello!', [
        'method'  => -20,
      ]);
    });
  }

  public function testAddFileTimestamp() : void {
    # get timezone offset
    # $ofs = \DateTimeZone::getOffset(\DateTime::getTimezone());
    $ofs = 4 * 3600; # FIXME: hard-coded to EDT for now

    # get time from 2 hours ago (round to even number of seconds)
    $time = ((time() - (2 * 3600)) >> 1) << 1;

    # get test time
    $expected_time = $time + $ofs;

    $this->with_temp_zip(function(ZipStream &$zip) use ($time) {
      $zip->add_file('hello.txt', 'hello!', [
        'time' => $time,
      ]);
    }, function($zip_path) use ($expected_time) {
      $zip = $this->open_archive($zip_path);
      $st = $zip->statName('hello.txt');

      $this->assertEquals($expected_time, $st['mtime']);
    });
  }

  public function testAddFileCRC() : void {
    $data = 'hello!';

    # calculate crc32b of file data
    $hash = hash('crc32b', $data, true);

    # pack expected crc as integer
    $expected_crc = (
      (ord($hash[0]) << 24) |
      (ord($hash[1]) << 16) |
      (ord($hash[2]) << 8) |
      (ord($hash[3]))
    );

    $this->with_temp_zip(function(ZipStream &$zip) use ($data) {
      $zip->add_file('hello.txt', $data);
    }, function($zip_path) use ($expected_crc) {
      $zip = $this->open_archive($zip_path);
      $st = $zip->statName('hello.txt');

      $this->assertEquals($expected_crc, $st['crc']);
    });
  }

  public function testAddFileWithMethodStore() : void {
    $data = file_get_contents(__FILE__);

    $this->with_temp_zip(function(ZipStream &$zip) use ($data) {
      $zip->add_file('test.php', $data, [
        'method' => \Pablotron\ZipStream\Methods::STORE,
      ]);
    }, function($zip_path) use ($data) {
      $zip = $this->open_archive($zip_path);

      $this->assertEquals(
        sha1($data),
        sha1($zip->getFromName('test.php'))
      );
    });
  }

  public function testAddFileWithMethodDeflate() : void {
    $data = file_get_contents(__FILE__);

    $this->with_temp_zip(function(ZipStream &$zip) use ($data) {
      $zip->add_file('test.php', $data, [
        'method' => \Pablotron\ZipStream\Methods::DEFLATE,
      ]);
    }, function($zip_path) use ($data) {
      $zip = $this->open_archive($zip_path);

      $this->assertEquals(
        sha1($data),
        sha1($zip->getFromName('test.php'))
      );
    });
  }
};
