<?php
/*
 * (c) 2013/5/9- yoya@awm.jp
 */

function unpack_once($v, $f) { $t = unpack($v, $f) ; return $t[1]; }

// SWF basic data types

function swfRead_u32($data, &$offset) {
    return ord($data[$offset++]) + ord($data[$offset++]) * 0x100 +
        ord($data[$offset++]) * 0x10000 + ord($data[$offset++]) * 0x1000000;
}

function swfRead_string($data, &$offset) {
    $termOffset = strpos($data, "\0", $offset);
    $len = $termOffset - $offset;
    $offset = $termOffset + 1; // $str + "0"
    return substr($data, $offset, $len);
}

function swfSkip_string($data, &$offset) {
    $termOffset = strpos($data, "\0", $offset);
    $offset = $termOffset + 1;
}

function swfparse($data) {
    $swf = array();
    $swf['data'] = $data;
    $swf['sig'] = substr($data, 0, 3);
    $swf['version'] = ord($data[3]);
    $fileLength = unpack_once('V', substr($data, 4, 4));
    if ($swf['sig'] === 'CWS') {
        $data = substr($data, 0, 8) . gzuncompress(substr($data, 8));
        $swf['data'] = $data;
    } else if ($swf['sig'] === 'ZWS') {
        $data = substr($data, 0, 8) . lzuncompress(substr($data, 8));
        $swf['data'] = $data;
    }
    $nbit = ord($data[8]) >> 3; // nbit of rectangle
    $movieheaderLength = ceil((5 + 4 * $nbit)/8) + 4;
    $swf['movieheaderLength'] = $movieheaderLength;
    $swf['movieOffset'] = 8 + $movieheaderLength;
    $offset = $swf['movieOffset'];
    $tagRefs = array();
    while ($offset + 1 < $fileLength) {
        $tag = array('offset' => $offset);
        $tag_and_length = unpack_once('v', substr($data, $offset, 2));
        $tag['code'] = $tag_and_length >> 6;
        $payloadLength = $tag_and_length & 0x3f;
        if ($payloadLength < 0x3f) {
            $payloadOffset = $offset + 2;
            $length = 2 + $payloadLength;
        } else {
            $payloadLength = unpack_once('V', substr($data, $offset + 2, 4));
            $payloadOffset = $offset + 2 + 4;
            $length = 2 + 4 + $payloadLength;
        }
        $tag['length'] = $length;
        $tag['payloadOffset'] = $payloadOffset;
        $tag['payloadLength'] = $payloadLength;
        $offset += $length;
        $tagRefs [] = $tag;
    }
    $swf['tagRefs'] = $tagRefs;
    return $swf;
}

function swfbuild($swf) {
    $data = $swf['data'];
    $tagList = array();
    $tagRefs = array();
    $mergeFirstTag = null;
    $prevFirstTag = array();

    foreach ($swf['tagRefs'] as $tag) { // bulk copy optimize
        if (array_key_exists('offset', $tag) && ($tag['code'] !== 0)) {
            if (is_null($mergeFirstTag)) {
                $mergeFirstTag = $tag;
            }
        } else {
            if (! is_null($mergeFirstTag)) {
                $offset = $mergeFirstTag['offset'];
                $mergedLength = $prev['offset'] + $prev['length'] - $offset;
                $tagRefs [] = array('offset' => $offset, 
                                    'length' => $mergedLength);
                $mergeFirstTag = null;
            }
            $tagRefs [] = $tag;
        }
        $prev = $tag;
    }
/*    var_dump($tagRefs);
    $tagRefs = $swf['tagRefs'];
*/
    foreach ($tagRefs as $tag) { // must be 
        if (array_key_exists('offset', $tag)) {
            $tagList []= substr($data, $tag['offset'], $tag['length']);
        } else if (array_key_exists('data', $tag)) {
            $tagList []= $tag['data'];
        } else { // payloadData;
            $payloadLength = strlen($tag['payloadData']);
            if ($payloadLength < 0x3f) {
                $tag_and_length = ($tag['code'] << 6) + $payloadLength;
                $tagHeader = pack('v', $tag_and_length);
            } else {
                $tag_and_length = ($tag['code'] << 6) + 0x3f;
                $tagHeader = pack('vV', $tag_and_length, $payloadLength);
            }
            $tagList []= $tagHeader . $tag['payloadData'];
        }
    }
    $movie = substr($data, 8, $swf['movieheaderLength']).join('', $tagList);
    $length = 8 + strlen($movie);
    $sig = $swf['sig'];
    if ($sig === 'CWS') {
        $movie = gzcompress($movie);
    } else if ($sig === 'ZWS') {
        $movie = lzcompress($movie);
    }
    return $sig.pack('CV', $swf['version'], $length) . $movie;
}
