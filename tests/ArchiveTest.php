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
    $this->with_temp_file(function(string $zip_path) {
      ZipStream::send($zip_path, function(ZipStream &$zip) {
        $zip->add_file('hello.txt', 'hello!');
      }, [
        'output' => new FileWriter(),
      ]);

      # open archive
      $zip = $this->open_archive($zip_path);

      # read hello.txt, check text
      $this->assertEquals(
        'hello!',
        $zip->getFromName('hello.txt')
      );
    });
  }

  public function testStreamWriter() {
    $this->with_temp_file(function(string $zip_path) {
      $fh = fopen($zip_path, 'wb');
      if ($fh === false) {
        throw new Exception("fopen() failed");
      }

      ZipStream::send($zip_path, function(ZipStream &$zip) {
        $zip->add_file('hello.txt', 'hello!');
      }, [
        'output' => new StreamWriter($fh),
      ]);

      # close stream
      fclose($fh);

      # open archive
      $zip = $this->open_archive($zip_path);

      # read hello.txt, check text
      $this->assertEquals(
        'hello!',
        $zip->getFromName('hello.txt')
      );
    });
  }

  public function testArchiveComment() : void {
    $this->with_temp_file(function($zip_path) {
      $comment = 'test archive comment';

      # write archive
      ZipStream::send($zip_path, function(ZipStream &$zip) {
        $zip->add_file('hello.txt', 'hello!');
      }, [
        'comment' => $comment,
        'output'  => new FileWriter(),
      ]);

      # open archive
      $zip = $this->open_archive($zip_path);

      # read hello.txt, check text
      $this->assertEquals(
        $comment,
        $zip->getArchiveComment()
      );
    });
  }

  public function testLongArchiveComment() : void {
    $this->expectException(\Pablotron\ZipStream\CommentError::class);

    $this->with_temp_file(function($zip_path) {
      $comment = str_repeat('x', 0xFFFF);

      # write archive
      ZipStream::send($zip_path, function(ZipStream &$zip) {
        $zip->add_file('hello.txt', 'hello!');
      }, [
        'comment' => $comment,
        'output'  => new FileWriter(),
      ]);
    });
  }

  public function testInvalidOutput() : void {
    $this->expectException(\Pablotron\ZipStream\Error::class);

    $this->with_temp_file(function($zip_path) {
      # write archive with invalid writer
      ZipStream::send($zip_path, function(ZipStream &$zip) {
        $zip->add_file('hello.txt', 'hello!');
      }, [
        'output' => 'bad writer',
      ]);
    });
  }

  public function testInvalidMethod() : void {
    $this->expectException(\Pablotron\ZipStream\UnknownMethodError::class);

    $this->with_temp_file(function($zip_path) {
      # write archive with invalid compression method
      ZipStream::send($zip_path, function(ZipStream &$zip) {
        $zip->add_file('hello.txt', 'hello!');
      }, [
        'method'  => 100,
        'output'  => new FileWriter(),
      ]);
    });
  }
};
