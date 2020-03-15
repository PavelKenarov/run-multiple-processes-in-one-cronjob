<?php

$type = empty($argv[1])? $_GET['type'] : $argv[1];

switch ($type) {
    case 'zizito3':
        include(dirname(__FILE__) . '/../xml_feeds.php');
        if(empty($argv[2])){
            new generateZizitoXmlFeeds($argv);
        }else{
            $id_shop    = empty($argv[2]) ? 3 : $argv[2];
            $id_lang    = empty($argv[3]) ? 1 : $argv[3];
            $currency   = empty($argv[4]) ? '' : $argv[4];
            new Zizito3($id_shop, $id_lang, $currency);
        }
        break;
}