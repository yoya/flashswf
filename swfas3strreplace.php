<?php
/*
 * (c) 2013/5/8- yoya@awm.jp
 */

require_once 'flashswf.php';
require_once 'as3abc.php';

function swfas3strreplace(&$swf, $replaceTable) {
    $data = $swf['data'];
    $nTotalReplaced = 0;
    foreach ($swf['tagRefs'] as &$tag) {
        if ($tag['code'] !== 82) {
            continue; // skip if is not DoABC tag.
        }
        $offset = $tag['payloadOffset'];
        /*
         * DoABC fields
         */
        $offset += 4; // u32 skip
        $name = swfSkip_string($data, $offset);
        /*
         * ABC ByteCode
         */
        // version
        $offset += 2 + 2; // minor_version , major_version
        // constant pool
        $int_count = abcRead_u30($data, $offset);
        for ($i = 1 ; $i < $int_count ; $i++) {
            $integer = abcSkip_s32($data, $offset);
        }
        $uint_count = abcRead_u30($data, $offset);
        for ($i = 1 ; $i < $uint_count ; $i++) {
            $uinteger = abcSkip_u32($data, $offset);
        }
        $double_count = abcRead_u30($data, $offset);
        for ($i = 1 ; $i < $double_count ; $i++) {
            $offset += 8; // skip d64
        }
        /*
         * replace string
         */
        $offsetStartOfString = $offset;
        $string_count = abcRead_u30($data, $offset);
        $replacedStrings = array();
        $nReplaced = 0;
        for ($i = 1 ; $i < $string_count ; $i++) {
            $string = abcRead_string($data, $offset);
//            echo "\t[$i]: $string\n";
            if (isset($replaceTable[$string])) {
                $replacedStrings []= $replaceTable[$string];
                $nReplaced++;
                $nTotalReplaced++;
            } else {
                $replacedStrings []= $string;
            }
        }
        $offsetNextOfString = $offset;
        if ($nReplaced === 0) {
            continue; // do nothing. if no modified.
        }
        /*
         * rebuild DoABC tag;
         */
        $stringList = array();
        for ($i = 0 ; $i < $string_count - 1 ; $i++) {
            $string = $replacedStrings[$i];
            $stringList []= abcWrite_u30(strlen($string)).$string;
        }        
        $tag['payloadData'] = substr($data, $tag['payloadOffset'], $offsetStartOfString - $tag['payloadOffset']) .
            abcWrite_u30($string_count) . join('', $stringList) .
            substr($data, $offsetNextOfString, $tag['payloadOffset'] + $tag['payloadLength'] - $offsetNextOfString);
        unset($tag['offset']);  unset($tag['length']); 
        unset($tag['payloadOffset']);  unset($tag['payloadLength']); 
    }
    return $nTotalReplaced;
}

// main

if ($argc < 4) {
    fputs(STDERR, "Usage: swfas3strreplace.php <swf> <from> <to> [<from2> <to2> [...]]\n");
    exit (1);
}

$data = file_get_contents($argv[1]);

$replaceTable = array();
for ($i = 2 ; ($i + 1) < $argc ; $i += 2) {
    $replaceTable[$argv[$i]] = $argv[$i+1];
}

$swf = swfparse($data, $replaceTable);
// $swf['data'] = "datalen:".strlen($swf['data']); var_dump($swf);

$n = swfas3strreplace($swf, $replaceTable);

echo  swfbuild($swf);
