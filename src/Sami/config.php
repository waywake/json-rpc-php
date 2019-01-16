<?php
/**
 * Created by PhpStorm.
 * User: dongwei
 * Date: 2019/1/14
 * Time: 6:46 PM
 */
//$config = require_once __DIR__.'/../../../../../config/rpc.php';
//$dir = $config['rpc_path'];
require_once __DIR__.'/../../../../../public/index.php';
$app_name = env('APP_NAME');
var_dump($app_name);
exit();
$document_path = $config['document_path'];


$iterator = \Symfony\Component\Finder\Finder::create()->files()->name('*.php')->in($dir);
return new \Sami\Sami($iterator,[
    'theme'             => 'default',//sami安装后默认的有一套文档页面风格模板，名称是default。位置在：vendor/sami/sami/Sami/Resources/themes/下。
    'title'             => 'Paidian-rpc',//项目名称
    'build_dir'             => $document_path,//设置文档生成后的保存路径
    'cache_dir'             => $document_path.'/cache/',//设置文档生成时的缓存路径
    'default_opened_level'  => 2
]);
