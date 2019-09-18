<?php

namespace doyzheng\yii2dao;

use yii\base\Model;
use yii\base\UnknownClassException;
use yii\base\UnknownMethodException;
use yii\db\ActiveQuery;
use yii\db\Transaction;
use yii\helpers\ArrayHelper;

/**
 * 数据访问层静态方法类
 * Class StaticDao
 * @mixin Dao
 * @package doyzheng\yii2dao
 * @method static string getPk()
 * @method static ActiveQuery find()
 * @method static ActiveQuery getQuery($where = [], $fields = '', $order = '')
 * @method static array getWhere($where)
 * @method static array getErrors()
 * @method static array getError()
 * @method static bool setErrors($error)
 * @method static Transaction beginTransaction()
 * @method static int getAutoIncrement()
 * @method static string getDbName()
 * @method static string getTableName()
 * @method static string getDefaultOrder()
 * @method static bool setSql($sql)
 * @method static array getSql()
 * @method static string getLastSql()
 * @method static array getAttributes()
 * @method static array get($where = [], $fields = '', $order = '')
 * @method static array getAll($where = [], $fields = '', $order = '')
 * @method static array getPage($where = [], $page = '1', $limit = '', $fields = '', $order = '')
 * @method static int add($data)
 * @method static int[] addAll($data)
 * @method static int batchInsert($data)
 * @method static bool update($where, $data)
 * @method static bool updateAll($where, $data)
 * @method static bool delete($where)
 * @method static bool deleteAll($where, $params = [])
 * @method static int count($where = [], $fields = '')
 * @method static int sum($where, $fields)
 * @method static int min($where, $field)
 * @method static int max($where, $field)
 * @method static bool inc($where, $field, $step = 1)
 * @method static bool dec($where, $field, $step = 1)
 * @method static mixed raw($value)
 */
abstract class StaticDao
{
    
    /**
     * @var string 关联模型类名
     */
    protected static $modelClass;
    
    /**
     * @var array 基本查询条件
     */
    protected static $baseWhere = [];
    
    /**
     * @var bool
     */
    protected static $asArray = true;
    
    /**
     * 禁止new实例对象
     * StaticDao constructor.
     */
    private function __construct()
    {
    }
    
    /**
     * 魔术方法(调用静态方法)
     * @param $method_name
     * @param $args
     * @return array|mixed
     * @throws UnknownClassException
     */
    public static function __callStatic($method_name, $args)
    {
        return static::callStatic($method_name, $args);
    }
    
    /**
     * 调用静态方法
     * @param string $method_name
     * @param array  $args
     * @return mixed
     * @throws UnknownClassException
     */
    public static function callStatic($method_name, $args)
    {
        return static::toArray(self::call($method_name, $args));
    }
    
    /**
     * 调用Dao实例方法
     * @param string $method_name
     * @param array  $args
     * @return mixed
     * @throws UnknownClassException
     */
    private static function call($method_name, $args)
    {
        $dao = self::getDao();
        if ($dao->hasMethod($method_name)) {
            return call_user_func_array([$dao, $method_name], $args);
        }
        // Dao方法不存在
        throw new UnknownMethodException($method_name);
    }
    
    /**
     * 获取数据访问层对象实例(单例)
     * @return Dao
     * @throws UnknownClassException
     */
    public static function getDao()
    {
        $id  = 'Dao' . static::$modelClass;
        $dao = Container::get($id);
        if (!$dao) {
            $dao = new Dao([
                'modelClass' => static::modelClass(),
                'baseWhere'  => static::baseWhere(),
            ]);
            Container::set($id, $dao);
        }
        return $dao;
    }
    
    /**
     * model转换到数组
     * @param array $result
     * @return array
     * @throws UnknownClassException
     */
    protected static function toArray($result)
    {
        if (static::asArray()) {
            if ($result instanceof Model) {
                return ArrayHelper::toArray($result);
            }
            if (is_array($result) && isset($result[0]) && $result[0] instanceof Model) {
                return ArrayHelper::toArray($result);
            }
        }
        return $result;
    }
    
    /**
     * 查询结果转换为数组
     * @param bool $value
     * @return bool
     */
    public static function asArray($value = null)
    {
        if ($value !== null) {
            static::$asArray = $value;
        }
        return static::$asArray;
    }
    
    /**
     * 获取模型层类名
     * @param object $modelClass
     * @return string
     * @throws UnknownClassException
     */
    public static function modelClass($modelClass = null)
    {
        if ($modelClass) {
            if (is_object($modelClass)) {
                static::$modelClass = $modelClass;
                static::getDao()->setModelClass($modelClass);
            } else {
                throw new UnknownClassException($modelClass);
            }
        }
        return static::$modelClass;
    }
    
    /**
     * 设置基础查询条件
     * @param array $baseWhere
     * @return array
     * @throws UnknownClassException
     */
    public static function baseWhere(array $baseWhere = [])
    {
        if ($baseWhere) {
            static::$baseWhere = $baseWhere;
            static::getDao()->setBaseWhere($baseWhere);
        }
        return static::$baseWhere;
    }
    
}
