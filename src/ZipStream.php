<?php
declare(strict_types = 1);

namespace Pablotron\ZipStream;

const VERSION = '0.3.0';

final class Methods {
  const STORE = 0;
  const DEFLATE = 2;
};

namespace Pablotron\ZipStream\Errors;

class Error extends \Exception { };
final class FileError extends Error {
  public $file_name;

  public function __construct(string $file_name, string $message) {
    $this->file_name = $file_name;
    parent::__construct($message);
  }
};

final class PathError extends Error {
  public $file_name;

  public function __construct(string $file_name, string $message) {
    $this->file_name = $file_name;
    parent::__construct($message);
  }
};

namespace Pablotron\ZipStream\Writers;

interface Writer {
  public function set(string $key, string $val);
  public function open();
  public function write(string $data);
  public function close();
};

final class HTTPResponseWriter implements Writer {
  private $args = [];

  public function set(string $key, string $val) {
    $this->args[$key] = $val;
  }

  public function open() {
    # TODO: send http headers
  }

  public function write(string $data) {
    echo $data;
  }

  public function close() {
    # ignore
  }
};

final class FileWriter implements Writer {
  const STATE_INIT = 0;
  const STATE_OPEN = 1;
  const STATE_CLOSED = 2;
  const STATE_ERROR = 3;

  public $path;
  private $fh;

  public function __construct($path) {
    $this->state = self::STATE_INIT;
    $this->path = $path;
  }

  public function set(string $key, string $val) {
    # ignore metadata
  }

  public function open() {
    # open output file
    $this->fh = @fopen($this->path, 'wb');
    if (!$this->fh) {
      throw new Errors\FileError($path, "couldn't open file");
    }

    # set state
    $this->state = self::STATE_OPEN;
  }

  public function write(string $data) {
    # check state
    if ($this->state != self::STATE_OPEN) {
      throw new Errors\Error("invalid output state");
    }

    # write data
    $len = fwrite($this->fh, $data);

    # check for error
    if ($len === false) {
      $this->state = self::STATE_ERROR;
      throw new Errors\FileError($this->path, 'fwrite() failed');
    }
  }

  public function close() {
    # check state
    if ($this->state == self::STATE_CLOSED) {
      return;
    } else if ($this->state != self::STATE_OPEN) {
      throw new Errors\Error("invalid output state");
    }

    # close file handle
    @fclose($this->fh);

    # set state
    $this->state = self::STATE_CLOSED;
  }
};

namespace Pablotron\ZipStream;
#
# Convert a UNIX timestamp to a DOS time/date.
#
final class DateTime {
  public $time,
         $dos_time,
         $dos_date;

  static $DOS_EPOCH = [
    'year'    => 1980,
    'mon'     => 1,
    'mday'    => 1,
    'hours'   => 0,
    'minutes' => 0,
    'seconds' => 0,
  ];

  public function __construct($time) {
    $this->time = $time;
    # get date array for timestamp
    $d = getdate($time);

    # set lower-bound on dates
    if ($d['year'] < 1980) {
      $d = self::$DOS_EPOCH;
    }

    # remove extra years from 1980
    $d['year'] -= 1980;

    $this->dos_date = ($d['year'] << 9) | ($d['mon'] << 5) | ($d['mday']);
    $this->dos_time = ($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1);
  }
};

final class Entry {
  const STATE_INIT = 0;
  const STATE_DATA = 1;
  const STATE_CLOSED = 2;
  const STATE_ERROR = 3;

  public $output,
         $pos,
         $name,
         $method,
         $time,
         $comment,
         $uncompressed_size,
         $compressed_size,
         $hash;

  private $len,
          $date_time,
          $hash_context,
          $state;

  public function __construct(
    object &$output, # FIXME: constrain to stream interface
    int $pos,
    string $name,
    int $method,
    int $time,
    string $comment
  ) {
    $this->output = $output;
    $this->pos = $pos;
    $this->name = $name;
    $this->method = $method;
    $this->time = $time;
    $this->comment = $comment;

    $this->uncompressed_size = 0;
    $this->compressed_size = 0;
    $this->state = self::STATE_INIT;
    $this->len = 0;
    $this->date_time = new DateTime($time);

    # init hash context
    $this->hash_context = hash_init('crc32b');

    # sanity check path
    $this->check_path($name);
  }

  public function write(string &$data) {
    try {
      # check entry state
      if ($this->state != self::STATE_DATA) {
        throw new Errors\Error("invalid entry state");
      }

      # update output size
      $this->uncompressed_size += strlen($data);

      # update hash context
      hash_update($this->hash_context, $data);

      if ($this->method === Methods::DEFLATE) {
        $compressed_data = gzdeflate($data);
        $this->compressed_size += strlen($compressed_data);
      } else if ($this->method === Methods::STORE) {
        $compressed_data = $data;
        $this->compressed_size += strlen($data);
      } else {
        throw new Errors\Error('invalid entry method');
      }

      # write compressed data to output
      return $this->output->write($compressed_data);
    } catch (Exception $e) {
      $this->state = self::STATE_ERROR;
      throw $e;
    }
  }

  ########################
  # local header methods #
  ########################

  public function write_local_header() {
    # check state
    if ($this->state != self::STATE_INIT) {
      throw new Errors\Error("invalid entry state");
    }

    # get entry header, update entry length
    $data = $this->get_local_header();

    # write entry header
    $this->output->write($data);

    # set state
    $this->state = self::STATE_DATA;

    # return header length
    return strlen($data);
  }

  const ENTRY_VERSION_NEEDED = 62;
  const ENTRY_BIT_FLAGS = 0b100000001000;

  private function get_local_header() {
    # build extra data
    $extra_data = pack('vv',
      0x01,                       # zip64 extended info header ID (2 bytes)
      0                           # field size (2 bytes)
    );

    # build and return local file header
    return pack('VvvvvvVVVvv',
      0x04034b50,                 # local file header signature (4 bytes)
      self::ENTRY_VERSION_NEEDED, # version needed to extract (2 bytes)
      self::ENTRY_BIT_FLAGS,      # general purpose bit flag (2 bytes)
      $this->method,              # compression method (2 bytes)
      $this->date_time->dos_time, # last mod file time (2 bytes)
      $this->date_time->dos_date, # last mod file date (2 bytes)
      0,                          # crc-32 (4 bytes, zero, in footer)
      0,                          # compressed size (4 bytes, zero, in footer)
      0,                          # uncompressed size (4 bytes, zero, in footer)
      strlen($this->name),        # file name length (2 bytes)
      strlen($extra_data)         # extra field length (2 bytes)
    ) . $this->name . $extra_data;
  }

  ########################
  # local footer methods #
  ########################

  public function write_local_footer() {
    # check state
    if ($this->state != self::STATE_DATA) {
      $this->state = self::STATE_ERROR;
      throw new Errors\Error("invalid entry state");
    }

    # finalize hash context
    $this->hash = hash_final($this->hash_context, true);
    $this->hash_context = null;

    # get footer
    $data = $this->get_local_footer();

    # write footer to output
    $this->output->write($data);

    # set state
    $this->state = self::STATE_CLOSED;

    # return footer length
    return strlen($data);
  }

  private function get_local_footer() {
    return pack('VVPP',
      0x08074b50,                 # data descriptor signature (4 bytes)
      $this->hash,                # crc-32 (4 bytes)
      $this->compressed_size,     # compressed size (8 bytes, zip64)
      $this->uncompressed_size    # uncompressed size (8 bytes, zip64)
    );
  }

  ##########################
  # central header methods #
  ##########################

  private function get_central_extra_data() {
    $r = [];

    if ($this->uncompressed_size >= 0xFFFFFFFF) {
      # append 64-bit uncompressed size
      $r[] = pack('P', $this->uncompressed_size);
    }

    if ($this->compressed_size >= 0xFFFFFFFF) {
      # append 64-bit compressed size
      $r[] = pack('P', $this->compressed_size);
    }

    if ($this->pos >= 0xFFFFFFFF) {
      # append 64-bit file offset
      $r[] = pack('P', $this->pos);
    }

    # build result
    $r = join('', $r);

    if (strlen($r) > 0) {
      $r = pack('vv',
        0x01,                     # zip64 ext. info extra tag (2 bytes)
        strlen($r)                # size of this extra block (2 bytes)
      ) . $r;
    }

    # return packed data
    return $r;
  }


  private function get_central_header() {
    $extra_data = $this->get_central_extra_data();

    # get sizes and offset
    $compressed_size = ($this->compressed_size >= 0xFFFFFFFF) ? 0xFFFFFFFF : $compressed_size;
    $uncompressed_size = ($this->uncompressed_size >= 0xFFFFFFFF) ? 0xFFFFFFFF : $uncompressed_size;
    $pos = ($this->pos >= 0xFFFFFFFF) ? 0xFFFFFFFF : $pos;

    # pack and return central header
    return pack('VvvvvvvVVVvvvvvVV',
      0x02014b50,                 # central file header signature (4 bytes)
      self::ENTRY_VERSION_NEEDED, # FIXME: version made by (2 bytes)
      self::ENTRY_VERSION_NEEDED, # version needed to extract (2 bytes)
      self::ENTRY_BIT_FLAGS,      # general purpose bit flag (2 bytes)
      $this->method,              # compression method (2 bytes)
      $this->date_time->dos_time, # last mod file time (2 bytes)
      $this->date_time->dos_date, # last mod file date (2 bytes)
      $this->hash,                # crc-32 (4 bytes)
      $compressed_size,           # compressed size (4 bytes)
      $uncompressed_size,         # uncompressed size (4 bytes)
      strlen($this->name),        # file name length (2 bytes)
      strlen($extra_data),        # extra field length (2 bytes)
      strlen($this->comment),     # file comment length (2 bytes)
      0,                          # disk number start (2 bytes)
      0,                          # TODO: internal file attributes (2 bytes)
      0,                          # TODO: external file attributes (4 bytes)
      $pos                        # relative offset of local header (4 bytes)
    ) . $this->name . $extra_data . $this->comment;
  }

  ###################
  # utility methods #
  ###################

  private function check_path(string $path) {
    # make sure path is non-null
    if (!$path) {
      throw new Errors\PathError($path, "null path");
    }

    # check for empty path
    if (!strlen($path)) {
      throw new Errors\PathError($path, "empty path");
    }

    # check for long path
    if (strlen($path) > 65535) {
      throw new Errors\PathError($path, "path too long");
    }

    # check for leading slash
    if (!$path[0] == '/') {
      throw new Errors\PathError($path, "path contains leading slash");
    }

    # check for trailing slash
    if (preg_match('/\\$/', $path)) {
      throw new Errors\PathError($path, "path contains trailing slash");
    }

    # check for double slashes
    if (preg_match('/\/\//', $path)) {
      throw new Errors\PathError($path, "path contains double slashes");
    }

    # check for double dots
    if (preg_match('/\.\./', $path)) {
      throw new Errors\PathError($path, "relative path");
    }
  }
};

final class ZipStream {
  const VERSION = '0.3.0';

  const STATE_INIT = 0;
  const STATE_ENTRY = 1;
  const STATE_CLOSED = 2;
  const STATE_ERROR = 3;

  # stream chunk size
  const READ_BUF_SIZE = 8192;

  public $name;
  private $args,
          $output,
          $pos = 0,
          $entries = [],
          $paths = [];

  static $ARCHIVE_DEFAULTS = [
    'method'  => Methods::DEFLATE,
    'comment' => '',
    'type'    => 'application/zip',
    'header'  => true,
  ];

  static $FILE_DEFAULTS = [
    'comment' => '',
  ];

  public function __construct(string $name, array &$args = []) {
    try {
      $this->state = self::STATE_INIT;
      $this->name = $name;
      $this->args = array_merge(self::$ARCHIVE_DEFAULTS, [
        'time' => time(),
      ], $args);

      # initialize output
      if (isset($args['output']) && $args['output']) {
        # use specified output writer
        $this->output = $args['output'];
      } else {
        # no output set, create default response writer
        $this->output = new Writers\HTTPResponseWriter();
      }

      # set output metadata
      $this->output->set('name', $this->name);
      $this->output->set('type', $this->args['type']);

      # open output
      $this->output->open();
    } catch (Exception $e) {
      $this->state = self::STATE_ERROR;
      throw $e;
    }
  }

  public function add_file(
    string $dst_path,
    string $data,
    array $args = []
  ) {
    $this->add($dst_path, function(Entry &$e) use (&$data) {
      # write data
      $e->write($data);
    }, array_merge(self::$FILE_DEFAULTS, $args));
  }

  public function add_file_from_path(
    string $dst_path,
    string $src_path,
    array &$args = []
  ) {
    # get file time
    if (!isset($args['time'])) {
      # get file mtime
      $time = @filemtime($src_path);
      if ($time === false) {
        throw new Errors\FileError($src_path, "couldn't get file mtime");
      }

      # save file mtime
      $args['time'] = $time;
    }

    # close input stream
    $args['close'] = true;

    # open input stream
    $fh = @fopen($src_path, 'rb');
    if (!$fh) {
      throw new Errors\FileError($src_path, "couldn't open file");
    }

    # read input
    $this->add_stream($dst_path, $fh, $args);
  }

  public function add_stream(
    string $dst_path,
    object &$src, # FIXME: limit to input stream
    array &$args = []
  ) {
    $this->add($dst_path, function(Entry &$e) use (&$src, &$args) {
      # read input
      while (!feof($src)) {
        # read chunk
        $buf = @fread($src, READ_BUF_SIZE);

        # check for error
        if ($buf === false) {
          throw new Errors\Error("file read error");
        }

        # write chunk to entry
        $e->write($buf);
      }

      # close input
      if (isset($args['close']) && $args['close']) {
        @fclose($src);
      }
    }, $args);
  }

  public function add(
    string $dst_path,
    callable $cb,
    array $args = []
  ) {
    # check state
    if ($this->state != self::STATE_INIT) {
      throw new Errors\Error("invalid output state");
    }

    # check for duplicate path
    if (isset($this->paths[$dst_path])) {
      throw new Errors\Error("duplicate path: $dst_path");
    }
    $this->paths[$dst_path] = true;

    # merge arguments with defaults
    $args = array_merge(self::$FILE_DEFAULTS, $args);

    try {
      # get compression method
      $method = $this->get_entry_method($args);

      # create new entry
      $e = new Entry(
        $this->output,
        $this->pos,
        $dst_path,
        $method,
        $this->get_entry_time($args),
        $args['comment']
      );

      # add to entry list
      $this->entries[] = $e;

      # set state
      $this->state = self::STATE_ENTRY;

      # write entry local header
      $header_len = $e->write_local_header();

      # pass entry to callback
      $cb($e);

      # write entry local footer
      $footer_len = $e->write_local_footer();

      # update output position
      $this->pos += $header_len + $e->compressed_size + $footer_len;

      # set state
      $this->state = self::STATE_INIT;
    } catch (Exception $e) {
      # set error state, re-throw exception
      $this->state = self::STATE_ERROR;
      throw $e;
    }
  }

  public function close() {
    try {
      if ($this->state != self::STATE_INIT) {
        throw new Errors\Error("invalid archive state");
      }

      # TODO: write cdr
      # TODO: write archive footer

      # close output
      $this->output->close();

      # return total archive length
      return $this->pos;
    } catch (Exception $e) {
      $this->state = self::STATE_ERROR;
      throw $e;
    }
  }

  public static function send(string $name, callable $cb, array &$args = []) {
    # create archive
    $zip = new self($name, $args);

    # pass archive to callback
    $cb($zip);

    # close archive and return total number of bytes written
    return $zip->close();
  }

  ###################
  # utility methods #
  ###################

  private function get_entry_time(array &$args) {
    if (isset($args['time'])) {
      return $args['time'];
    } else if (isset($this->args['time'])) {
      return $this->args['time'];
    } else {
      return time();
    }
  }

  private function get_entry_method(array &$args) {
    if (isset($args['method'])) {
      return $args['method'];
    } else if (isset($this->args['method'])) {
      return $this->args['method'];
    } else {
      return METHOD_DEFLATE;
    }
  }
};
