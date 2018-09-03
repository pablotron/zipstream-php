<?php
/**
 * Dynamically generate streamed zip archives.
 *
 * @author Paul Duncan <pabs@pablotron.org>
 * @copyright 2007-2018 Paul Duncan <pabs@pablotron.org>
 * @license MIT
 * @package Pablotron\ZipStream
 */

declare(strict_types = 1);

namespace Pablotron\ZipStream;

/**
 * Current version of ZipStream.
 *
 * @api
 */
const VERSION = '0.3.0';

/**
 * Version needed to extract.
 *
 * @internal
 *
 * @example "examples/09-version.php"
 */
const VERSION_NEEDED = 45;

/**
 * Base class for all exceptions raised by ZipStream.
 */
class Error extends \Exception { };

/**
 * Comment length error.
 */
final class CommentError extends Error { };

/**
 * Deflate context error.
 */
final class DeflateError extends Error { };

/**
 * Unknown compression method error.
 */
final class UnknownMethodError extends Error {
  /** @var int Unknown compression method. */
  public $method;

  /**
   * Create a new UnknownMethod error.
   *
   * @param int $method Unknown compression method.
   */
  public function __construct(int $method) {
    $this->method = $method;
    parent::__construct('unknown compression method');
  }
};

/**
 * File related error.
 */
final class FileError extends Error {
  /** @var string Name of fail in which error occurred. */
  public $file_name;

  /**
   * Create a new UnknownMethod error.
   *
   * @param string $file_name File name.
   * @param string $message Error message.
   */
  public function __construct(string $file_name, string $message) {
    $this->file_name = $file_name;
    parent::__construct($message);
  }
};

/**
 * Path validation error.
 */
final class PathError extends Error {
  /** @var string string Invalid file name. */
  public $file_name;

  /**
   * Create a new UnknownMethod error.
   *
   * @param string $file_name File name.
   * @param string $message Error message.
   */
  public function __construct(string $file_name, string $message) {
    $this->file_name = $file_name;
    parent::__construct($message);
  }
};

/**
 * Valid compression methods.
 */
final class Methods {
  /** Store file without compression. */
  const STORE = 0;

  /** Store file using DEFLATE compression. */
  const DEFLATE = 8;

  /**
   * Check for supported compression method.
   *
   * @internal
   *
   * @param int $method Compression method.
   *
   * @return void
   *
   * @throw UnsupportedMethodError if compression method is unsupported.
   */
  static public function check(int $method) : void {
    if ($method != Methods::DEFLATE && $method != Methods::STORE) {
      throw new UnknownMethodError($method);
    }
  }
};

/**
 * Abstract interface for stream output.
 *
 * @api
 *
 * @see FileWriter, HTTPResponseWriter
 */
interface Writer {
  /**
   * Set metadata for generated archive.
   *
   * @param string $key Metadata key (one of "name" or "type").
   * @param string $val Metadata value.
   *
   * @return void
   */
  public function set(string $key, string $val) : void;

  /**
   * Flush metadata and begin streaming archive contents.
   *
   * @return void
   */
  public function open() : void;

  /**
   * Write archive contents.
   *
   * @param string $data Archive file data.
   *
   * @return void
   */
  public function write(string $data) : void;

  /**
   * Finish writing archive data.
   *
   * @return void
   */
  public function close() : void;
};

/**
 * Stream generated archive as an HTTP response.
 *
 * @api
 *
 * Streams generated zip archive as an HTTP response.  This is the
 * default writer used by ZipStream if none is provided.
 *
 * @see Writer
 */
final class HTTPResponseWriter implements Writer {
  /**
   * @var array Hash of metadata.
   * @internal
   */
  private $args = [];

  /**
   * Set metadata for generated archive.
   *
   * @param string $key Metadata key (one of "name" or "type").
   * @param string $val Metadata value.
   *
   * @return void
   */
  public function set(string $key, string $val) : void {
    $this->args[$key] = $val;
  }

  /**
   * Flush metadata and begin streaming archive contents.
   *
   * @return void
   */
  public function open() : void {
    # write response headers
    foreach ($this->get_headers as $key => $val) {
      header("$key: $val");
    }
  }

  /**
   * Write archive contents.
   *
   * @param string $data Archive file data.
   *
   * @return void
   */
  public function write(string $data) : void {
    echo $data;
  }

  /**
   * Finish writing archive data.
   *
   * @return void
   */
  public function close() : void {
    # ignore
  }

  /**
   * Get HTTP headers.
   *
   * @internal
   *
   * @return array Hash of HTTP headers.
   */
  private function get_headers() : array {
    # build pre-RFC6266 file name
    $old_name = preg_replace('/[^a-z0-9_.-]+/', '_', $this->args['name']);

    # build URI-encoded (RFC6266) file name
    $new_name = urlencode($this->args['name']);

    # build and return response headers
    return [
      'Content-Type' => $this->args['type'],
      'Content-Disposition' => join('; ', [
        'attachment',
        "filename=\"{$old_name}\"",
        "filename*=UTF-8''{$new_name}"
      ]),

      'Pragma' => 'public',
      'Cache-Control' => 'public, must-revalidate',
      'Content-Transfer-Encoding' => 'binary',
    ];
  }
};

/**
 * Write generated zip archive to a local file.
 *
 * @api
 *
 * @example "examples/06-file_writer.php"
 */
final class FileWriter implements Writer {
  /** @var string Output file path. */
  public $path;

  /**
   * @var resource Output file handle.
   * @internal
   */
  private $fh;

  const FILE_WRITER_STATE_INIT = 0;
  const FILE_WRITER_STATE_OPEN = 1;
  const FILE_WRITER_STATE_CLOSED = 2;
  const FILE_WRITER_STATE_ERROR = 3;

  /**
   * Create a new FileWriter.
   *
   * @api
   *
   */
  public function __construct() {
    # set state
    $this->state = self::FILE_WRITER_STATE_INIT;
  }

  /**
   * Set metadata for generated archive.
   *
   * @param string $key Metadata key (one of "name" or "type").
   * @param string $val Metadata value.
   *
   * @return void
   */
  public function set(string $key, string $val) : void {
    # check state
    if ($this->state !== self::FILE_WRITER_STATE_INIT) {
      # set state, raise error
      $this->state = self::FILE_WRITER_STATE_ERROR;
      throw new Error("invalid file writer state");
    }

    if ($key == 'name') {
      # save name
      $this->path = $val;
    } else {
      # ignore other metadata
    }
  }

  /**
   * Flush metadata and begin streaming archive contents.
   *
   * @return void
   *
   * @throw FileError if output archive could not be opened.
   */
  public function open() : void {
    # check state
    if ($this->state !== self::FILE_WRITER_STATE_INIT) {
      # set state, raise error
      $this->state = self::FILE_WRITER_STATE_ERROR;
      throw new Error("invalid file writer state");
    }

    # open output file
    $this->fh = @fopen($this->path, 'wb');
    if (!$this->fh) {
      throw new FileError($path, "couldn't open file");
    }

    # set state
    $this->state = self::FILE_WRITER_STATE_OPEN;
  }

  /**
   * Write archive contents.
   *
   * @param string $data Archive file data.
   *
   * @return void
   */
  public function write(string $data) : void {
    # check state
    if ($this->state != self::FILE_WRITER_STATE_OPEN) {
      # set state, raise error
      $this->state = self::FILE_WRITER_STATE_ERROR;
      throw new Error("invalid output state");
    }

    # write data
    $len = fwrite($this->fh, $data);

    # check for error
    if ($len === false) {
      # set state, raise error
      $this->state = self::FILE_WRITER_STATE_ERROR;
      throw new FileError($this->path, 'fwrite() failed');
    }
  }

  /**
   * Finish writing archive data.
   *
   * @return void
   */
  public function close() : void {
    # check state
    if ($this->state == self::FILE_WRITER_STATE_CLOSED) {
      return;
    } else if ($this->state != self::FILE_WRITER_STATE_OPEN) {
      # set state, raise error
      $this->state = self::FILE_WRITER_STATE_ERROR;
      throw new Error("invalid output state");
    }

    # close file handle
    @fclose($this->fh);

    # set state
    $this->state = self::FILE_WRITER_STATE_CLOSED;
  }
};

/**
 * Write generated zip archive to a stream.
 *
 * @api
 *
 * @example "examples/07-stream_writer.php"
 */
final class StreamWriter implements Writer {
  /** @var resource Output stream. */
  public $stream;

  const STREAM_WRITER_STATE_INIT = 0;
  const STREAM_WRITER_STATE_OPEN = 1;
  const STREAM_WRITER_STATE_CLOSED = 2;
  const STREAM_WRITER_STATE_ERROR = 3;

  /**
   * Create a new StreamWriter.
   *
   * @api
   *
   * @param resource $stream Output stream.
   *
   * @example "examples/07-stream_writer.php"
   */
  public function __construct($stream) {
    # check stream
    if (!is_resource($stream)) {
      $this->state = self::STREAM_WRITER_STATE_ERROR;
      throw new Error('stream is not a resource');
    }

    # set state, cache stream
    $this->state = self::STREAM_WRITER_STATE_INIT;
    $this->stream = $stream;
  }

  /**
   * Set metadata for generated archive.
   *
   * *Note:* This method is not used for StreamWriter.
   *
   * @param string $key Metadata key (one of "name" or "type").
   * @param string $val Metadata value.
   *
   * @return void
   */
  public function set(string $key, string $val) : void {
    # ignore metadata
  }

  /**
   * Flush metadata and begin streaming archive contents.
   *
   * *Note:* This method is not used for StreamWriter.
   *
   * @return void
   *
   * @throw FileError if output archive could not be opened.
   */
  public function open() : void {
    # set state
    $this->state = self::STREAM_WRITER_STATE_OPEN;
  }

  /**
   * Write archive contents.
   *
   * @param string $data Archive file data.
   *
   * @return void
   */
  public function write(string $data) : void {
    # check state
    if ($this->state != self::STREAM_WRITER_STATE_OPEN) {
      # set state, raise error
      $this->state = self::STREAM_WRITER_STATE_ERROR;
      throw new Error("invalid output state");
    }

    # write data
    $len = fwrite($this->stream, $data);

    # check for error
    if ($len === false) {
      # set state, raise error
      $this->state = self::STREAM_WRITER_STATE_ERROR;
      throw new Error('fwrite() failed');
    }
  }

  /**
   * Finish writing archive data.
   *
   * @return void
   */
  public function close() : void {
    # check state
    if ($this->state == self::STREAM_WRITER_STATE_CLOSED) {
      return;
    } else if ($this->state != self::STREAM_WRITER_STATE_OPEN) {
      # set state, raise error
      $this->state = self::STREAM_WRITER_STATE_ERROR;
      throw new Error("invalid output state");
    }

    # flush output
    if (!@fflush($this->stream)) {
      # set state, raise error
      $this->state = self::STREAM_WRITER_STATE_ERROR;
      throw new Error("fflush() failed");
    }

    # set state
    $this->state = self::STREAM_WRITER_STATE_CLOSED;
  }
};

/**
 * Convert a UNIX timestamp into DOS date and time components.
 *
 * @internal
 *
 * @example "examples/08-datetime.php"
 */
final class DateTime {
  /**
   * @var int $time Input UNIX timestamp.
   * @var int $dos_time Output DOS timestamp.
   * @var int $dos_date Output DOS datestamp.
   */
  public $time,
         $dos_time,
         $dos_date;

  /**
   * Minimal date/time representable by a DOS timestamp.
   * @internal
   */
  static $DOS_EPOCH = [
    'year'    => 1980,
    'mon'     => 1,
    'mday'    => 1,
    'hours'   => 0,
    'minutes' => 0,
    'seconds' => 0,
  ];

  /**
   * Convert a UNIX timestamp into DOS date and time components.
   *
   * @param int $time Input UNIX timestamp.
   *
   * @example "examples/08-datetime.php"
   */
  public function __construct(int $time) {
    $this->time = $time;

    # get date array for timestamp
    $d = getdate($time);

    # set lower-bound on dates
    if ($d['year'] < 1980) {
      $d = self::$DOS_EPOCH;
    }

    # remove extra years from 1980
    $d['year'] -= 1980;

    $this->dos_date = (
      (($d['year'] & 0x7F) << 9) |
      (($d['mon'] & 0xF) << 5) |
      ($d['mday'] & 0x1F)
    );

    $this->dos_time = (
      (($d['hours'] & 0x3F) << 11) |
      (($d['minutes'] & 0x3F) << 5) |
      (($d['seconds'] & 0x3F) >> 1)
    );
  }
};

/**
 * CRC32b hash context wrapper.
 * @internal
 */
final class Hasher {
  /** @var int $hash Output hash result. */
  public $hash;

  /** @var object $ctx Internal hash context. */
  private $ctx;

  /**
   * Create a new Hasher.
   */
  public function __construct() {
    $this->ctx = hash_init('crc32b');
  }

  /**
   * Write data to Hasher.
   *
   * @param string $data Input data.
   * @return void
   * @throw Error if called after close().
   */
  public function write(string $data) : void {
    if ($this->ctx !== null) {
      # update hash context
      hash_update($this->ctx, $data);
    } else {
      throw new Error('hash context already finalized');
    }
  }

  /**
   * Create hash of input data.
   *
   * Finalize the internal has context and return a CRC32b hash of the
   * given input data.
   *
   * @return int CRC32b hash of input data.
   */
  public function close() : int {
    if ($this->ctx !== null) {
      # finalize hash context
      $d = hash_final($this->ctx, true);
      $this->ctx = null;

      # encode hash as uint32_t
      # (FIXME: endian issue?)
      $this->hash = (
        (ord($d[0]) << 24) |
        (ord($d[1]) << 16) |
        (ord($d[2]) << 8) |
        (ord($d[3]))
      );
    }

    # return encoded result
    return $this->hash;
  }
};

/**
 * Abstract base class for data filter methods.
 *
 * @internal
 * @see StoreFilter, DeflateFilter
 */
abstract class DataFilter {
  /** @var Writer output writer */
  private $output;

  /**
   * Create a new DataFilter bound to the given Writer.
   *
   * @param Writer $output Output writer.
   */
  public function __construct(Writer &$output) {
    $this->output = $output;
  }

  /**
   * Write data to data filter.
   *
   * @param string $data Output data.
   * @return int Number of bytes written.
   */
  public function write(string $data) : int {
    $this->output->write($data);
    return strlen($data);
  }

  /**
   * Close this data filter.
   *
   * @return int Number of bytes written.
   */
  public abstract function close() : int;
}

/**
 * Data filter for files using the store compression method.
 *
 * @internal
 * @see DataFilter, DeflateFilter
 */
final class StoreFilter extends DataFilter {
  /**
   * Close this data filter.
   *
   * @return int Number of bytes written.
   */
  public function close() : int {
    return 0;
  }
};

/**
 * Data filter for files using the deflate compression method.
 *
 * @internal
 *
 * @see DataFilter, StoreFilter
 */
final class DeflateFilter extends DataFilter {
  /** @var object $ctx Deflate context. */
  private $ctx;

  /**
   * Create a new DeflateFilter bound to the given Writer.
   *
   * @param Writer $output Output writer.
   *
   * @throw DeflateError If initializing deflate context fails.
   */
  public function __construct(Writer &$output) {
    # init parent
    parent::__construct($output);

    # init deflate context, check for error
    $this->ctx = deflate_init(ZLIB_ENCODING_RAW);
    if ($this->ctx === false) {
      throw new DeflateError('deflate_init() failed');
    }
  }

  /**
   * Write data to data filter.
   *
   * @param string $data Output data.
   *
   * @return int Number of bytes written.
   *
   * @throw DeflateError If writing to deflate context fails.
   * @throw Error If this filter was already closed.
   */
  public function write(string $data) : int {
    # check state
    if (!$this->ctx) {
      # filter already closed
      throw new Error('Filter already closed');
    }

    # write data to deflate context, check for error
    $compressed_data = deflate_add($this->ctx, $data, ZLIB_NO_FLUSH);
    if ($compressed_data === false) {
      throw new DeflateError('deflate_add() failed');
    }

    # pass data to parent
    return parent::write($compressed_data);
  }

  /**
   * Close this data filter.
   *
   * @return int Number of bytes written.
   *
   * @throw DeflateError If writing to deflate context fails.
   * @throw Error If this filter was already closed.
   */
  public function close() : int {
    # check state
    if (!$this->ctx) {
      # filter already closed
      throw new Error('Filter already closed');
    }

    # finalize context, flush remaining data
    $compressed_data = deflate_add($this->ctx, '', ZLIB_FINISH);
    if ($compressed_data === false) {
      throw new DeflateError('deflate_add() failed');
    }

    # clear deflate context
    $this->ctx = null;

    # write remaining data, return number of bytes written
    return parent::write($compressed_data);
  }
};

/**
 * Internal representation for a zip file.
 *
 * @internal
 *
 * @see DataFilter, StoreFilter
 */
final class Entry {
  const ENTRY_STATE_INIT = 0;
  const ENTRY_STATE_DATA = 1;
  const ENTRY_STATE_CLOSED = 2;
  const ENTRY_STATE_ERROR = 3;

  /**
   * @var Writer $output
   *   Reference to output writer.
   * @var int $pos
   *   Offset from start of file of entry local header.
   * @var string $name
   *   Name of file.
   * @var int $method
   *   Compression method.
   * @var int $time
   *   File creation time (UNIX timestamp).
   * @var string $comment
   *   File comment.
   * @var int $uncompressed_size
   *   Raw file size, in bytes.  Only valid after file has been
   *   read completely.
   * @var int $compressed_size
   *   Compressed file size, in bytes.  Only valid after file has
   *   been read completely.
   * @var int $hash
   *   CRC32b hash of file contents.  Only valid after file has
   *   been read completely.
   */
  public $output,
         $pos,
         $name,
         $method,
         $time,
         $comment,
         $uncompressed_size,
         $compressed_size,
         $hash;

  /**
   * @var int $len
   *   File length, in bytes.  Only valid after file has been read
   *   completely.
   * @var DateTime $date_time
   *   Date and time converter for this file.
   * @var Hasher $hasher
   *   Internal hasher for this file.
   * @var int $state
   *   Entry state.
   */
  private $len,
          $date_time,
          $hasher,
          $state;

  /**
   * Create a new entry object.
   *
   * @internal
   *
   * @param Writer $output
   *   Reference to output writer.
   * @param int $pos
   *   Offset from start of file of entry local header.
   * @param string $name
   *   Name of file.
   * @param int $method
   *   Compression method.
   * @param int $time
   *   File creation time (UNIX timestamp).
   * @param string $comment
   *   File comment.
   *
   * @throw Error if compression method is unknown.
   * @throw PathError if compression method is unknown.
   */
  public function __construct(
    Writer &$output,
    int $pos,
    string $name,
    int $method,
    int $time,
    string $comment
  ) {
    # set state
    $this->state = self::ENTRY_STATE_INIT;

    $this->output = $output;
    $this->pos = $pos;
    $this->name = $name;
    $this->method = $method;
    $this->time = $time;
    $this->comment = $comment;

    # check comment length
    if (strlen($comment) >= 0xFFFF) {
      $this->state = self::ENTRY_STATE_ERROR;
      throw new CommentError('entry comment too long');
    }

    $this->uncompressed_size = 0;
    $this->compressed_size = 0;
    $this->len = 0;
    $this->date_time = new DateTime($time);

    # init hash context
    $this->hasher = new Hasher();

    # init data filter
    if ($this->method == Methods::DEFLATE) {
      $this->filter = new DeflateFilter($this->output);
    } else if ($this->method == Methods::STORE) {
      $this->filter = new StoreFilter($this->output);
    } else {
      $this->state = self::ENTRY_STATE_ERROR;
      throw new UnknownMethodError($this->method);
    }

    # sanity check path
    $this->check_path($name);
  }

  /**
   * Write data to file entry.
   *
   * @api
   *
   * @param string $data Output file data.
   *
   * @throw Error if entry state is invalid.
   *
   * @return int Number of bytes written to output.
   */
  public function write(string $data) : int {
    try {
      # check entry state
      if ($this->state != self::ENTRY_STATE_DATA) {
        throw new Error("invalid entry state");
      }

      # update output size
      $this->uncompressed_size += strlen($data);

      # update hash context
      $this->hasher->write($data);

      $len = $this->filter->write($data);
      $this->compressed_size += $len;

      # return length
      return $len;
    } catch (Exception $e) {
      $this->state = self::ENTRY_STATE_ERROR;
      throw $e;
    }
  }

  ########################
  # local header methods #
  ########################

  /**
   * Write local file header.
   *
   * @internal
   *
   * @throw Error if entry state is invalid.
   *
   * @return int Number of bytes written to output.
   */
  public function write_local_header() : int {
    # check state
    if ($this->state != self::ENTRY_STATE_INIT) {
      throw new Error("invalid entry state");
    }

    # get entry header, update entry length
    $data = $this->get_local_header();

    # write entry header
    $this->output->write($data);

    # set state
    $this->state = self::ENTRY_STATE_DATA;

    # return header length
    return strlen($data);
  }

  const ENTRY_VERSION_NEEDED = 45;
  const ENTRY_BIT_FLAGS = 0b100000001000;

  /**
   * Create local file header for this entry.
   *
   * @return string Packed local file header.
   */
  private function get_local_header() : string {
    # build extra data
    $extra_data = pack('vv',
      0x01,                       # zip64 extended info header ID (2 bytes)
      0                           # field size (2 bytes)
    );

    # build and return local file header
    return pack('VvvvvvVVVvv',
      0x04034b50,                 # local file header signature (4 bytes)
      VERSION_NEEDED,             # version needed to extract (2 bytes)
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

  /**
   * Write local file footer (e.g. data descriptor).
   *
   * @internal
   *
   * @throw Error if entry state is invalid.
   *
   * @return int Number of bytes written to output.
   */
  public function write_local_footer() : int {
    # check state
    if ($this->state != self::ENTRY_STATE_DATA) {
      $this->state = self::ENTRY_STATE_ERROR;
      throw new Error("invalid entry state");
    }

    # finalize hash context
    $this->hash = $this->hasher->close();
    $this->hasher = null;

    # flush remaining data
    $this->compressed_size += $this->filter->close();

    # get footer
    $data = $this->get_local_footer();

    # write footer to output
    $this->output->write($data);

    # set state
    $this->state = self::ENTRY_STATE_CLOSED;

    # return footer length
    return strlen($data);
  }

  /**
   * Create local file footer (data descriptor) for this entry.
   *
   * @return string Packed local file footer.
   */
  private function get_local_footer() : string {
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

  /**
   * Write central directory entry for this file to output writer.
   *
   * @internal
   *
   * @return int Number of bytes written to output.
   */
  public function write_central_header() : int {
    $data = $this->get_central_header();
    $this->output->write($data);
    return strlen($data);
  }

  /**
   * Create extra data for central directory entry for this file.
   *
   * @return string Packed extra data for central directory entry.
   */
  private function get_central_extra_data() : string {
    $r = [];

    # check uncompressed size for overflow
    if ($this->uncompressed_size >= 0xFFFFFFFF) {
      # append 64-bit uncompressed size
      $r[] = pack('P', $this->uncompressed_size);
    }

    # check compressed size for overflow
    if ($this->compressed_size >= 0xFFFFFFFF) {
      # append 64-bit compressed size
      $r[] = pack('P', $this->compressed_size);
    }

    # check offset for overflow
    if ($this->pos >= 0xFFFFFFFF) {
      # append 64-bit file offset
      $r[] = pack('P', $this->pos);
    }

    # build result
    $r = join('', $r);

    if (strlen($r) > 0) {
      # has overflow, so generate zip64 info
      $r = pack('vv',
        0x01,                     # zip64 ext. info extra tag (2 bytes)
        strlen($r)                # size of this extra block (2 bytes)
      ) . $r;
    }

    # return packed data
    return $r;
  }

  /**
   * Create central directory entry for this file.
   *
   * @return string Packed central directory entry.
   */
  private function get_central_header() : string {
    $extra_data = $this->get_central_extra_data();

    # get compressed size, uncompressed size, and offset
    $compressed_size = ($this->compressed_size >= 0xFFFFFFFF) ? 0xFFFFFFFF : $this->compressed_size;
    $uncompressed_size = ($this->uncompressed_size >= 0xFFFFFFFF) ? 0xFFFFFFFF : $this->uncompressed_size;
    $pos = ($this->pos >= 0xFFFFFFFF) ? 0xFFFFFFFF : $this->pos;

    # pack and return central header
    return pack('VvvvvvvVVVvvvvvVV',
      0x02014b50,                 # central file header signature (4 bytes)
      VERSION_NEEDED,             # FIXME: version made by (2 bytes)
      VERSION_NEEDED,             # version needed to extract (2 bytes)
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

  /**
   * Check file path.
   *
   * Verifies that the given file path satisfies the following
   * constraints:
   *
   * * Path is not null.
   * * Path not empty.
   * * Path is less than 65535 bytes in length.
   * * Path does not contain a leading slash.
   * * Path does not contain a trailing slash.
   * * Path does not contain double slashes.
   * * Path does not contain backslashes.
   * * Path is not a relative path.
   *
   * @param string $path Input file path.
   *
   * @return void
   *
   * @throw PathError if path is invalid.
   */
  private function check_path(string $path) : void {
    # make sure path is non-null
    if (!$path) {
      throw new PathError($path, "null path");
    }

    # check for empty path
    if (!strlen($path)) {
      throw new PathError($path, "empty path");
    }

    # check for long path
    if (strlen($path) >= 0xFFFF) {
      throw new PathError($path, "path too long");
    }

    # check for leading slash
    if ($path[0] == '/') {
      throw new PathError($path, "path contains leading slash");
    }

    # check for trailing slash
    if (preg_match('/\/$/', $path)) {
      throw new PathError($path, "path contains trailing slash");
    }

    # check for double slashes
    if (preg_match('/\/\//', $path)) {
      throw new PathError($path, "path contains double slashes");
    }

    # check for backslashes
    if (preg_match('/\\\\/', $path)) {
      throw new PathError($path, "path contains backslashes");
    }

    # check for relative path
    if (preg_match('/^\.\.|\\/\.\.\\/|\.\.$/', $path)) {
      throw new PathError($path, "relative path");
    }
  }
};

/**
 * Dynamically generate streamed zip archives.
 *
 * @api
 *
 * @example "examples/01-simple.php"
 */
final class ZipStream {
  /** @internal Initial stream state. */
  const STREAM_STATE_INIT = 0;
  /** @internal Writing an entry. */
  const STREAM_STATE_ENTRY = 1;
  /** @internal Stream is closed. */
  const STREAM_STATE_CLOSED = 2;
  /** @internal Encountered an error white streaming. */
  const STREAM_STATE_ERROR = 3;

  /** @internal Size, in bytes, of chunks to read from files. */
  const READ_BUF_SIZE = 8192;

  /** @var string Output archive name. */
  public $name;

  /**
   * @var array $args Hash of options.
   * @var Writer $output output Writer.
   * @var int $pos Current byte offset in output stream.
   * @var array $entries Array of archive entries.
   */
  private $args,
          $output,
          $pos = 0,
          $entries = [],
          $paths = [];

  /**
   * Default archive options.
   * @internal
   */
  static $ARCHIVE_DEFAULTS = [
    'method'  => Methods::DEFLATE,
    'comment' => '',
    'type'    => 'application/zip',
    'header'  => true,
  ];

  /**
   * Default file options.
   * @internal
   */
  static $FILE_DEFAULTS = [
    'comment' => '',
  ];

  /**
   * Create a new ZipStream object.
   *
   * @param string $name Output archive name.
   * @param array $args Hash of output options (optional).
   *
   * @example "examples/01-simple.php"
   */
  public function __construct(string $name, array $args = []) {
    try {
      # set state
      $this->state = self::STREAM_STATE_INIT;

      # set name and args
      $this->name = $name;
      $this->args = array_merge(self::$ARCHIVE_DEFAULTS, [
        'time' => time(),
      ], $args);

      # check archive method
      Methods::check($this->args['method']);

      # check archive comment length
      if (strlen($this->args['comment']) >= 0xFFFF) {
        throw new CommentError('archive comment too long');
      }

      # initialize output
      if (isset($args['output']) && $args['output']) {
        if (!is_subclass_of($args['output'], Writer::class)) {
          throw new Error('output must implement Writer interface');
        }

        # use specified output writer
        $this->output = $args['output'];
      } else {
        # no output set, create default response writer
        $this->output = new HTTPResponseWriter();
      }

      # set output metadata
      $this->output->set('name', $this->name);
      $this->output->set('type', $this->args['type']);

      # open output
      $this->output->open();
    } catch (Exception $e) {
      $this->state = self::STREAM_STATE_ERROR;
      throw $e;
    }
  }

  /**
   * Add file to output archive.
   *
   * @param string $dst_path Destination path in output archive.
   * @param string $data File contents.
   * @param array $args File options (optional).
   *
   * @return void
   *
   * @example "examples/01-simple.php"
   */
  public function add_file(
    string $dst_path,
    string $data,
    array $args = []
  ) : void {
    $this->add($dst_path, function(Entry &$e) use (&$data) {
      # write data
      $e->write($data);
    }, array_merge(self::$FILE_DEFAULTS, $args));
  }

  /**
   * Add file on the local file system to output archive.
   *
   * @param string $dst_path Destination path in output archive.
   * @param string $src_path Path to input file.
   * @param array $args File options (optional).
   *
   * @return void
   *
   * @example "examples/02-add_file_from_path.php"
   *
   * @throw FileError if the file could not be opened or read.
   */
  public function add_file_from_path(
    string $dst_path,
    string $src_path,
    array &$args = []
  ) : void {
    # get file time
    if (!isset($args['time'])) {
      # get file mtime
      $time = @filemtime($src_path);
      if ($time === false) {
        throw new FileError($src_path, "couldn't get file mtime");
      }

      # save file mtime
      $args['time'] = $time;
    }

    # close input stream
    $args['close'] = true;

    # open input stream
    $fh = @fopen($src_path, 'rb');
    if (!$fh) {
      throw new FileError($src_path, "couldn't open file");
    }

    # read input
    $this->add_stream($dst_path, $fh, $args);
  }

  /**
   * Add contents of resource stream to output archive.
   *
   * @param string $dst_path Destination path in output archive.
   * @param resource $src Input resource stream.
   * @param array $args File options (optional).
   *
   * @return void
   *
   * @example "examples/03-add_stream.php"
   *
   * @throw Error if $src is not a resource.
   * @throw Error if the resource could not be read.
   */
  public function add_stream(
    string $dst_path,
    &$src,
    array &$args = []
  ) : void {
    if (!is_resource($src)) {
      $this->state = self::STREAM_STATE_ERROR;
      throw new Error('source is not a resource');
    }

    $this->add($dst_path, function(Entry &$e) use (&$src, &$args) {
      # read input
      while (!feof($src)) {
        # read chunk
        $buf = @fread($src, self::READ_BUF_SIZE);

        # check for error
        if ($buf === false) {
          throw new Error("fread() error");
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

  /**
   * Dynamically write file contents to output archive.
   *
   * @param string $dst_path Destination path in output archive.
   * @param callable $cb Write callback.
   * @param array $args File options (optional).
   *
   * @return void
   *
   * @example "examples/04-add.php"
   *
   * @throw Error if the archive is in an invalid state.
   * @throw Error if the destination path already exists.
   */
  public function add(
    string $dst_path,
    callable $cb,
    array $args = []
  ) : void {
    # check state
    if ($this->state != self::STREAM_STATE_INIT) {
      throw new Error("invalid output state");
    }

    # check for duplicate path
    if (isset($this->paths[$dst_path])) {
      throw new Error("duplicate path: $dst_path");
    }
    $this->paths[$dst_path] = true;

    # merge arguments with defaults
    $args = array_merge(self::$FILE_DEFAULTS, $args);

    try {
      # create new entry
      $e = new Entry(
        $this->output,
        $this->pos,
        $dst_path,
        $this->get_entry_method($args),
        $this->get_entry_time($args),
        $args['comment']
      );

      # add to entry list
      $this->entries[] = $e;

      # set state
      $this->state = self::STREAM_STATE_ENTRY;

      # write entry local header
      $header_len = $e->write_local_header();

      # pass entry to callback
      $cb($e);

      # write entry local footer
      $footer_len = $e->write_local_footer();

      # update output position
      $this->pos += $header_len + $e->compressed_size + $footer_len;

      # set state
      $this->state = self::STREAM_STATE_INIT;
    } catch (Exception $e) {
      # set error state, re-throw exception
      $this->state = self::STREAM_STATE_ERROR;
      throw $e;
    }
  }

  /**
   * Finalize the output stream.
   *
   * @return int Total number of bytes written.
   *
   * @throw Error if the archive is in an invalid state.
   *
   * @example "examples/01-simple.php"
   */
  public function close() : int {
    try {
      if ($this->state != self::STREAM_STATE_INIT) {
        throw new Error("invalid archive state");
      }

      # cache cdr offset, write cdr, get cdr length
      $cdr_pos = $this->pos;
      $cdr_len = array_reduce($this->entries, function($r, $e) {
        return $r + $e->write_central_header();
      }, 0);

      # update position
      $this->pos += $cdr_len;

      # cache zip64 end of cdr position
      $zip64_cdr_pos = $this->pos;

      # write zip64 end cdr record
      $data = $this->get_zip64_end_of_central_directory_record($cdr_pos, $cdr_len);
      $this->output->write($data);
      $this->pos += strlen($data);

      # write zip64 end cdr locator
      $data = $this->get_zip64_end_of_central_directory_locator($zip64_cdr_pos);
      $this->output->write($data);
      $this->pos += strlen($data);

      # write end cdr record
      $data = $this->get_end_of_central_directory_record($cdr_pos, $cdr_len);
      $this->output->write($data);
      $this->pos += strlen($data);

      # close output
      $this->output->close();

      # return total archive length
      return $this->pos;
    } catch (Exception $e) {
      $this->state = self::STREAM_STATE_ERROR;
      throw $e;
    }
  }

  /**
   * Create an archive and send it using a single function.
   *
   * @param string $name Name of output archive.
   * @param callable $cb Context callback.
   * @param array $args Hash of archive options (optional).
   *
   * @example "examples/05-send.php"
   */
  public static function send(
    string $name,
    callable $cb,
    array $args = []
  ) : int {
    # create archive
    $zip = new self($name, $args);

    # pass archive to callback
    $cb($zip);

    # close archive and return total number of bytes written
    return $zip->close();
  }

  ####################################
  # central directory record methods #
  ####################################

  /**
   * Get Zip64 end of Central Directory Record (CDR)
   *
   * @param int $cdr_pos CDR offset, in bytes.
   * @param int $cdr_len Size of CDR, in bytes.
   *
   * @return string Packed Zip64 end of Central Directory Record.
   */
  private function get_zip64_end_of_central_directory_record(
    int $cdr_pos,
    int $cdr_len
  ) : string {
    # get entry count
    $num_entries = count($this->entries);

    return pack('VPvvVVPPPP',
      0x06064b50, # zip64 end of central dir signature (4 bytes)
      44,         # size of zip64 end of central directory record (8 bytes)
      VERSION_NEEDED, # FIXME: version made by (2 bytes)
      VERSION_NEEDED, # version needed to extract (2 bytes)
      0,              # number of this disk (4 bytes)
      0,              # number of the disk with the start of the central directory (4 bytes)
      $num_entries,   # total number of entries in the central directory on this disk (8 bytes)
      $num_entries,   # total number of entries in the central directory (8 bytes)
      $cdr_len,       # size of the central directory  (8 bytes)
      $cdr_pos        # offset of start of central directory with respect to the starting disk number (8 bytes)
      # zip64 extensible data sector (variable size)
      # (FIXME: is extensible data sector needed?)
    );
  }

  /**
   * Get Zip64 End of Central Directory Record (CDR) Locator.
   *
   * @param int $zip64_cdr_pos Zip64 End of CDR offset, in bytes.
   *
   * @return string Packed Zip64 End of CDR Locator.
   */
  private function get_zip64_end_of_central_directory_locator(
    int $zip64_cdr_pos
  ) : string {
    return pack('VVPV',
      0x07064b50,     # zip64 end of central dir locator signature (4 bytes)
      0,              # number of the disk with the start of the zip64 end of central directory (4 bytes)
      $zip64_cdr_pos, # relative offset of the zip64 end of central directory record (8 bytes)
      1               # total number of disks (4 bytes)
    );
  }

  /**
   * Get End of Central Directory Record (CDR) Locator.
   *
   * @param int $cdr_pos CDR offset, in bytes.
   * @param int $cdr_len CDR size, in bytes.
   *
   * @return string End of CDR Record.
   */
  private function get_end_of_central_directory_record(
    int $cdr_pos,
    int $cdr_len
  ) : string {
    # get entry count
    $num_entries = count($this->entries);
    if ($num_entries >= 0xFFFF) {
      # clamp entry count
      $num_entries = 0xFFFF;
    }

    # get/clamp cdr_len and cdr_pos
    $cdr_len = ($cdr_len >= 0xFFFFFFFF) ? 0xFFFFFFFF : $cdr_len;
    $cdr_pos = ($cdr_pos >= 0xFFFFFFFF) ? 0xFFFFFFFF : $cdr_pos;

    # get comment, check length
    $comment = $this->args['comment'];
    if (strlen($comment) >= 0xFFFF) {
      throw new CommentError('archive comment too long');
    }

    return pack('VvvvvVVv',
      0x06054b50,       # end of central dir signature (4 bytes)
      0,                # number of this disk (2 bytes)
      0,                # disk with the start of the central directory (2 bytes)
      $num_entries,     # number of entries in the central directory on this disk (2 bytes)
      $num_entries,     # number of entries in the central directory (2 bytes)
      $cdr_len,         # size of the central directory (4 bytes)
      $cdr_pos,         # offset of start of central directory with respect to the starting disk number (4 bytes)
      strlen($comment)  # .ZIP file comment length (2 bytes)
    ) . $comment;
  }

  ###################
  # utility methods #
  ###################

  /**
   * Get UNIX timestamp for given entry.
   *
   * @param array $args Entry options.
   *
   * @return int UNIX timestamp.
   */
  private function get_entry_time(array &$args) : int {
    if (isset($args['time'])) {
      # use entry time
      return $args['time'];
    } else if (isset($this->args['time'])) {
      # use archive time
      return $this->args['time'];
    } else {
      # fall back to current time
      return time();
    }
  }

  /**
   * Get compression method for given entry.
   *
   * @param array $args Entry options.
   *
   * @return int Compression method.
   */
  private function get_entry_method(array &$args) : int {
    if (isset($args['method'])) {
      $r = $args['method'];
    } else if (isset($this->args['method'])) {
      $r = $this->args['method'];
    } else {
      # fall back to default method
      $r = Methods::DEFLATE;
    }

    # check method
    Methods::check($r);

    # return method
    return $r;
  }
};
