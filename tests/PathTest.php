<?php
declare(strict_types = 1);

namespace Pablotron\ZipStream\Tests;

use \PHPUnit\Framework\TestCase;
use \Pablotron\ZipStream\ZipStream;

final class PathTest extends BaseTestCase {
  public function testAddEmptyPath() : void {
    $this->expectException(\Pablotron\ZipStream\PathError::class);

    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('', 'empty test');
    });
  }

  public function testAddLongPath() : void {
    $this->expectException(\Pablotron\ZipStream\PathError::class);

    $this->with_temp_zip(function(ZipStream &$zip) {
      $long_path = str_repeat('x', 65535);
      $zip->add_file($long_path, 'long path test');
    });
  }

  public function testAddPathWithLeadingSlash() : void {
    $this->expectException(\Pablotron\ZipStream\PathError::class);

    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('/foo', 'leading slash path test');
    });
  }

  public function testAddPathWithTrailingSlash() : void {
    $this->expectException(\Pablotron\ZipStream\PathError::class);

    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('foo/', 'trailing slash path test');
    });
  }

  public function testAddPathWithDoubleSlashes() : void {
    $this->expectException(\Pablotron\ZipStream\PathError::class);

    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('foo//bar', 'double slash path test');
    });
  }

  public function testAddPathWithBackslashes() : void {
    $this->expectException(\Pablotron\ZipStream\PathError::class);

    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('foo\\bar', 'backslash path test');
    });
  }

  public function testLeadingRelativePath() : void {
    $this->expectException(\Pablotron\ZipStream\PathError::class);

    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('../bar', 'leading relative path test');
    });
  }

  public function testMiddleRelativePath() : void {
    $this->expectException(\Pablotron\ZipStream\PathError::class);

    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('foo/../bar', 'middle relative path test');
    });
  }

  public function testTrailingRelativePath() : void {
    $this->expectException(\Pablotron\ZipStream\PathError::class);

    $this->with_temp_zip(function(ZipStream &$zip) {
      $zip->add_file('foo/../bar', 'trailing relative path test');
    });
  }

};
