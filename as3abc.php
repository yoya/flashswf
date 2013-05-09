<?php
/*
 * (c) 2011/5/9- yoya@awm.jp
 */

require_once 'flashswf.php';

// ABC primitive data types

function abcRead_u16($data, &$offset) {
    return ord($data[$offset++]) + ord($data[$offset++]) * 0x100;
}

function abcRead_u30($data, &$offset) {
    $v = 0;
    for ($i = 0 ; $i < 5 ; $i++) {
        $d = ord($data[$offset++]);
        $v += ($d & 0x7f) << (7 * $i);
        if (!($d & 0x80)) {
            break;
        }
    }
    return $v;
}

function abcRead_s32($data, &$offset) {
    $prevOffset = $offset;
    $v = abcRead_u30($data, &$offset);
    $byteLen = $offset - $prevOffset;
    if ($v >> (7 * $byteLen - 1)) { // sign bit
        $v = $v - (1 << (7 * $byteLen));
    }
    return $v;
}

function abcRead_u32($data, &$offset) {
    return abcRead_u30($data, $offset); // XXX
}

function abcRead_d64($data, &$offset) {
    $v = unpack('d', substr($data, $offset, 8)); // XXX: 64bit double
    $offset += 8;
    return $v[1];
}

function abcRead_string($data, &$offset) {
    $len = abcRead_u30($data, $offset);
    $str = substr($data, $offset, $len); $offset += $len;
    return $str;
}

function abcSkip_u30($data, &$offset) {
    for ($i = 0 ; $i < 5 ; $i++) {
        $d = ord($data[$offset++]);
        if (!($d & 0x80)) {
            break;
        }
    }
}

function abcSkip_u32($data, &$offset) { abcSkip_u30($data, &$offset); }
function abcSkip_s32($data, &$offset) { abcSkip_u30($data, &$offset); }

function abcWrite_u30($value) {
    $data = '';
    $v = 0;
    for ($i = 0 ; $i < 5 ; $i++) {
        $extendBit = ($value >= 0x7f)?0x80:0;
        $data .= chr($extendBit + ($value & 0x7f));
        if (! $extendBit) {
            break;
        }
        $value >>= 7;
    }
    return $data;
}


function swfas3dump($swf) {
    $data = $swf['data'];
    foreach ($swf['tagRefs'] as &$tag) {
        if ($tag['code'] !== 82) {
            continue; // skip if is not DoABC tag.
        }
        $offset = $tag['payloadOffset'];
        $payloadLength = $tag['payloadLength'];
        /*
         * DoABC fields
         */
        $flags = swfRead_u32($data, $offset);
        $name = swfRead_string($data, $offset);
        echo "flags:$flags name:$name\n";
        /*
         * ABC ByteCode
         */
        // version
        $minor_version = abcRead_u16($data, $offset);
        $major_version = abcRead_u16($data, $offset);
        echo "version:$major_version.$minor_version\n";
        // constant pool
        $int_count = abcRead_u30($data, $offset);
        echo "int_count:$int_count\n";
        for ($i = 1 ; $i < $int_count ; $i++) {
            $integer = abcRead_s32($data, $offset);
            echo "\t[$i]: $integer\n";
        }
        $uint_count = abcRead_u30($data, $offset);
        echo "uint_count:$uint_count\n";
        for ($i = 1 ; $i < $uint_count ; $i++) {
            $uinteger = abcRead_u32($data, $offset);
            echo "\t[$i]: $uinteger\n";
        }
        $double_count = abcRead_u30($data, $offset);
        echo "double_count:$double_count\n";
        for ($i = 1 ; $i < $double_count ; $i++) {
            $double = abcRead_d64($data, $offset);
            echo "\t[$i]: $double\n";
        }
        $string_count = abcRead_u30($data, $offset);
        echo "string_count:$string_count\n";
        for ($i = 1 ; $i < $string_count ; $i++) {
            $string = abcRead_string($data, $offset);
            echo "\t[$i]: $string\n";
        }
    }
}
