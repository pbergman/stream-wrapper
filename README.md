## Stream Wrapper

This little library was build to use an resource with [XMLWriter](http://php.net/manual/en/book.xmlwriter.php) and [XMLReader](http://php.net/manual/en/book.xmlreader.php) but could be used with everything that support an "URL like" path name as argument.

## Example
    
```
    require_once 'vendor/autoload.php';
    
    use PBergman\Stream\StreamWrapper;
    
    // createtmp buffer
    $fd = fopen('php://temp', 'w');
    
    // register buffer and wrapper protocol
    StreamWrapper::register($fd, 'foo');
    
    // create xml
    $writer = new \XMLWriter();
    $writer->openUri('wrapper://foo');
    $writer->setIndent(true);
    $writer->startElement('records');
    $writer->startElement('record');
    $writer->startElement('title');
    $writer->text('Some title...');
    $writer->endElement();
    $writer->endElement();
    $writer->endElement();
    $writer->flush();
    
    rewind($fd);
    var_dump(stream_get_contents($fd));
 
    // should output something like:
    // string(73) "<records>
    //  <record>
    //   <title>Some title...</title>
    //  </record>
    // </records>
    // "
```