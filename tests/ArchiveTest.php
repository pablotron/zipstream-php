<?php
declare(strict_types = 1);

namespace Pablotron\ZipStream\Tests;

use PHPUnit\Framework\TestCase;
use Pablotron\ZipStream\ZipStream;
use Pablotron\ZipStream\FileWriter;
use Pablotron\ZipStream\StreamWriter;

final class ArchiveTest extends BaseTestCase {
  public function testCreate() : void {
    $zip = new ZipStream('test.zip');

    $this->assertInstanceOf(
      ZipStream::class,
      $zip
    );
  }

  public function testFileWriter() {
    $this->with_temp_file(function(string $dst_path) {
      ZipStream::send($dst_path, function(ZipStream &$zip) {
        $zip->add_file('hello.txt', 'hello!');
      }, [
        'output' => new FileWriter(),
      ]);

      # open archive
      $zip = $this->open_archive($dst_path);

      # read hello.txt, check text
      $this->assertEquals(
        'hello!',
        $zip->getFromName('hello.txt')
      );
    });
  }

  public function testStreamWriter() {
    $this->with_temp_file(function(string $dst_path) {
      $fh = fopen($dst_path, 'wb');
      if ($fh === false) {
        throw new Exception("fopen() failed");
      }

      ZipStream::send($dst_path, function(ZipStream &$zip) {
        $zip->add_file('hello.txt', 'hello!');
      }, [
        'output' => new StreamWriter($fh),
      ]);

      # close stream
      fclose($fh);

      # open archive
      $zip = $this->open_archive($dst_path);

      # read hello.txt, check text
      $this->assertEquals(
        'hello!',
        $zip->getFromName('hello.txt')
      );
    });
  }

  public function testArchiveComment() : void {
    $this->with_temp_file(function($dst_path) {
      $comment = 'test archive comment';

      # write archive
      ZipStream::send($dst_path, function(ZipStream &$zip) {
        $zip->add_file('hello.txt', 'hello!');
      }, [
        'comment' => $comment,
        'output'  => new FileWriter(),
      ]);

      # open archive
      $zip = $this->open_archive($dst_path);

      # read hello.txt, check text
      $this->assertEquals(
        $comment,
        $zip->getArchiveComment()
      );
    });
  }
};
