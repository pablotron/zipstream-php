<?php

namespace Pablotron\ZipStream;

final class ZipStream {
  const VERSION = "0.2.0";

  public $zip_name;
  private $args;
  private $entries = array();

  static $DEFAULT_ARCHIVE_OPTIONS = [
    'method'  => 'deflate'
    'comment' => '',
    'header'  => true,
  ];

  static $DEFAULT_FILE_OPTIONS = [
    'comment' => '',
  ];

  public function __construct(
    string $zip_name,
    array $args = array()
  ) {
    $this->zip_name = $zip_name;
    $this->args = array_merge(
      self::$DEFAULT_ARCHIVE_OPTIONS,
      $args
    );
  }

  public function add_text(
    string $dst_path, 
    array $args = array()
  ) {
    $this->check_path($dst_path);
    $this->entries[] = pack('VvvvvvVVVvv'
  }

  public function add_path(
    string $dst_path,
    string $src_path = null,
    array $args = array()
  )

  public function add_stream(
    string $dst_path,
    $src_stream,
    array $args = array()
  )

  public function finish() {
  }

  public static function send($name, array $args, function $cb) {
    $zip = new self($name, $args);
    $cb($zip);
    $zip->finish();
  }

  private function check_path(string $path) {
    # make sure path is non-null
    if (!$path) {
      throw new Exception("null path");
    }

    # check for empty path
    if (!strlen($path)) {
      throw new Exception("empty path");
    }

    # check for long path
    if (strlen($path) > 65535) {
      throw new Exception("path too long");
    }

    # check for leading slash
    if (!$path[0] == '/') {
      throw new Exception("path contains leading slash");
    }

    # check for trailing slash
    if (preg_match('/\\$/', $path)) {
      throw new Exception("path contains trailing slash");
    }

    # check for double slashes
    if (preg_match('/\/\//', $path))
      throw new Exception("path contains double slashes");
    }

    # check for double dots
    if (preg_match('/\.\./', $path))
      throw new Exception("relative path");
    }
  }

};
