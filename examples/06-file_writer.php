<?php
declare(strict_types = 1);

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream classes
use Pablotron\ZipStream\ZipStream;
use Pablotron\ZipStream\FileWriter;

# save as "example.zip" in examples directory
$zip_path = __DIR__ . '/example.zip';

$zip = new ZipStream($zip_path, [
  'output' => new FileWriter(),
]);

# add a file named "hello.txt" to output archive, containing
# the string "hello world!"
$zip->add_file('hello.txt', 'hello world!');

# finalize archive
$zip->close();
