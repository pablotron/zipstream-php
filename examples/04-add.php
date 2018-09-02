<?php

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream class
use Pablotron\ZipStream\ZipStream;

# set source path of local file
$files = [
  'summary-01.txt' => '01-simple.php',
  'summary-04.txt' => '04-add.php',
];

# create the output archive named "example.zip"
$zip = new ZipStream('example.zip');

foreach ($files as $dst_path => $src_path) {
  $zip->add($dst_path, function(&$e) use ($src_path) {
    # build absolute path to source file
    $abs_src_path = __DIR__ . "/$src_path";

    # read file contents and get md5 hash
    $data = file_get_contents($abs_src_path);

    # build arguments
    $args = [
      'source'  => $src_path,
      'md5'     => md5($data),
      'sha1'    => sha1($data),
    ];

    # write lines to output archive
    foreach ($args as $key => $val) {
      # build line
      $line = "$key: $val\n";

      # write line to output file
      $e->write($line);
    }
  });
}

# finalize the output stream
$zip->close();
