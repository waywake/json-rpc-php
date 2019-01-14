<?php
/**
 * Created by PhpStorm.
 * User: dongwei
 * Date: 2019/1/14
 * Time: 4:22 PM
 */
$dir = __DIR__.'/../Server/';

$iterator = \Symfony\Component\Finder\Finder::create()->files()->name('*.php')->in($dir);

return new \Sami\Sami($iterator,[
    'theme'             => 'default',//sami安装后默认的有一套文档页面风格模板，名称是default。位置在：vendor/sami/sami/Sami/Resources/themes/下。
    'title'             => 'Mysic',//项目名称
    'build_dir'             => __DIR__.'/document/',//设置文档生成后的保存路径
    'cache_dir'             => __DIR__.'/document/cache/',//设置文档生成时的缓存路径
    'default_opened_level'  => 2
]);