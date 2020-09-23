<?php
/**
 * @author    Philip Bergman <pbergman@live.nl>
 * @copyright Philip Bergman
 */
namespace PBergman\Stream;

/**
 * Class StreamWrapper
 *
 * This is an wrapper that proxies all stream method calls to an inner registered resource, resources should be
 * registered with the StreamWrapper::register method which returns an id what can be used for uri path with
 * functions that support that (like chmod, chown, stat, fopen, file_exists, filesize etc.).
 *
 * Example:
 *
 *  $fd = fopen('php://temp', 'w+');
 *  $id = StreamWrapper::register($fd);
 *
 *  $writer = new \XMLWriter();
 *  $writer->openUri('wrapper://' . $id);
 *
 *  ....
 *
 *  rewind($fd);
 *  var_dump(stream_get_contents($fd));
 *
 *
 */
class StreamWrapper
{
    /** @var resource[]  */
    private static $resources;
    /** @var resource  */
    private $inner;

    /**
     * @param resource $resource
     * @param mixed $id
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function register($resource, $id = null)
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException(sprintf('Expected an resource got "%s".', gettype($resource)));
        }

        if (!in_array('wrapper', stream_get_wrappers())) {
            stream_wrapper_register('wrapper', self::class);
        }

        if (null === $id) {
            $id = sha1(time());
        }

        self::$resources[$id] = $resource;

        return $id;
    }

    /**
     * @param $path
     * @return resource
     */
    private function getInnerFromPath($path)
    {
        $id = substr($path, 10);

        if (!isset(self::$resources[$id])) {
            throw new \InvalidArgumentException('No resource registered for id: \'' . $id . '\'.');
        }

        return self::$resources[$id];
    }

    /**
     * @inheritdoc
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->inner = $this->getInnerFromPath($path);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function stream_close()
    {
        if (false !== $key = array_search($this->inner, self::$resources)) {
            unset(self::$resources[$key]);
        }

        return fclose($this->inner);
    }

    /**
     * @inheritdoc
     */
    public function stream_eof()
    {
        return feof($this->inner);
    }

    /**
     * @inheritdoc
     */
    public function stream_flush()
    {
        return fflush($this->inner);
    }

    /**
     * @inheritdoc
     */
    public function stream_lock($operation)
    {
        return flock($this->inner, $operation);
    }

    /**
     * @inheritdoc
     */
    public function stream_metadata($path, $option, $value)
    {
        $meta = stream_get_meta_data($this->getInnerFromPath($path));

        switch ($option) {
            case STREAM_META_TOUCH:
                touch($meta['uri'], ...$value);
                break;
            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
                chown($meta['uri'], ...$value);
                break;
            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
                chgrp($meta['uri'], ...$value);
                break;
            case STREAM_META_ACCESS:
                chmod($meta['uri'], ...$value);
                break;

        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function stream_read($count)
    {
        return fread($this->inner, $count);
    }

    /**
     * @inheritdoc
     */
    public function stream_seek($offset, $whence)
    {
        return 0 === fseek($this->inner, $offset, $whence);
    }

    /**
     * @inheritdoc
     */
    public function stream_set_option($option, $arg1, $arg2)
    {
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                return stream_set_blocking($this->inner, $arg1);
                break;
            case STREAM_OPTION_READ_TIMEOUT:
                return (null === $arg2) ? stream_set_timeout($this->inner, $arg1) : stream_set_timeout($this->inner, $arg1, $arg2);
                break;
            case STREAM_OPTION_WRITE_BUFFER:
                switch ($arg1) {
                    case STREAM_BUFFER_NONE:
                        return 0 === stream_set_read_buffer($this->inner, 0);
                        break;
                    case STREAM_BUFFER_FULL:
                        return 0 === stream_set_read_buffer($this->inner, $arg2);
                        break;
                    default:
                        return false;
                }
                break;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function stream_stat()
    {
        return fstat($this->inner);
    }

    /**
     * @inheritdoc
     */
    public function stream_tell()
    {
        return ftell($this->inner);
    }

    /**
     * @inheritdoc
     */
    public function stream_truncate($new_size)
    {
        return ftruncate($this->inner, $new_size);
    }

    /**
     * @inheritdoc
     */
    public function stream_write($data)
    {
        return fwrite($this->inner, $data);
    }

    /**
     * @inheritdoc
     */
    public function unlink($path)
    {
        return unlink(stream_get_meta_data($this->getInnerFromPath($path))['uri']);
    }

    /**
     * @inheritdoc
     */
    public function url_stat($path, $flags)
    {
        $inner = $this->getInnerFromPath($path);
        $meta  = stream_get_meta_data($inner);
        $quiet = (\STREAM_URL_STAT_QUIET === (\STREAM_URL_STAT_QUIET & $flags));

        if ('PHP' === $meta['wrapper_type'] && ('TEMP' === $meta['stream_type'] || 'MEMORY' === $meta['stream_type'])) {
            return ($quiet) ? @fstat($inner) ?? [] : fstat($inner);
        }

        if ((\STREAM_URL_STAT_LINK === (\STREAM_URL_STAT_LINK & $flags)) && is_link($meta['uri'])) {
            return ($quiet) ? @lstat($meta['uri']) ?? [] : lstat($meta['uri']);
        }

        return ($quiet) ? @stat($meta['uri']) ?? [] : stat($meta['uri']);
    }
}
