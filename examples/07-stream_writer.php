<?php
declare(strict_types = 1);

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream classes
use Pablotron\ZipStream\ZipStream;
use Pablotron\ZipStream\StreamWriter;

# save as "example.zip" in examples directory
$zip_path = __DIR__ . '/example.zip';

# open output stream
$out_stream = fopen($zip_path, 'wb');

# create zipstream
# NOTE: output archive name is ignored for StreamWriter
$zip = new ZipStream('', [
  'output' => new StreamWriter($out_stream),
]);

# add a file named "hello.txt" to output archive, containing
# the string "hello world!"
$zip->add_file('hello.txt', 'hello world!');

# finalize archive
$zip->close();

# close output stream
fclose($out_stream);
