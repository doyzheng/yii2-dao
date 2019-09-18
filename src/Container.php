<?php

namespace doyzheng\yii2dao;

/**
 * 容器类
 * Class Container
 * @package doyzheng\yii2dao
 */
class Container
{
    
    /**
     * 保存的实例对象池
     * @var array
     */
    private static $_instances = [];
    
    /**
     * 获取实例对象
     * @param string $id 实例Id
     * @return mixed
     */
    public static function get($id)
    {
        $id = self::buildId($id);
        return isset(self::$_instances[$id]) ? self::$_instances[$id] : null;
    }
    
    /**
     * 生成实例ID
     * @param string $id
     * @return string
     */
    private static function buildId($id)
    {
        return md5(serialize($id));
    }
    
    /**
     * 设置实例对象
     * @param string $id
     * @param mixed  $value
     * @return mixed
     */
    public static function set($id, $value)
    {
        self::$_instances[self::buildId($id)] = $value;
        return $value;
    }
}
