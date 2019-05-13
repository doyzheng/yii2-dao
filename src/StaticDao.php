<?php

namespace app\tests;

use yii\base\Model;
use yii\base\UnknownClassException;
use yii\base\UnknownMethodException;
use yii\db\ActiveQuery;
use yii\db\Transaction;
use yii\helpers\ArrayHelper;

/**
 * Class StaticDao
 * @package app\tests
 * @method static string getPk()
 * @method static ActiveQuery find()
 * @method static ActiveQuery getQuery($where = [], $fields = '', $order = '')
 * @method static array getWhere($where)
 * @method static array getErrors()
 * @method static void setErrors($error)
 * @method static Transaction beginTransaction()
 * @method static int getAutoIncrement()
 * @method static string getDbName()
 * @method static string getTableName()
 * @method static string getDefaultOrder()
 * @method static void setSql($sql)
 * @method static array getSql()
 * @method static string getLastSql()
 * @method static array getAttributes()
 * @method static array get($where = [], $fields = '', $order = '')
 * @method static array getAll($where = [], $fields = '', $order = '')
 * @method static array getPage($where = [], $page = '1', $limit = '', $fields = '', $order = '')
 * @method static int add($data)
 * @method static int addAll($data)
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
 *
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
    protected static $asArray = false;
    
    /**
     * 调用当前静态方法时传入的参数
     * @var array
     */
    protected static $currentCallArgs;
    
    /**
     * 数据访问层对象实例
     * @var Dao[]
     */
    private static $daoInstances;
    
    /**
     * 禁止new实例对象
     * StaticDao constructor.
     */
    private function __construct()
    {
    }
    
    /**
     * 魔术方法(调用静态方法)
     * @param $name
     * @param $args
     * @return array|mixed
     * @throws UnknownClassException
     */
    public static function __callStatic($name, $args)
    {
        return static::callStatic($name, $args);
    }
    
    /**
     * 调用静态方法
     * @param $name
     * @param $args
     * @return array|mixed
     * @throws UnknownClassException
     */
    public static function callStatic($name, $args)
    {
        // 当前调用方法
        static::$currentCallArgs = [$name, $args];
        // 调用静态方法之前回调
        static::beforeCall($name, $args);
        // 返回缓存数据
        $key = static::getCurrentCacheKey();
        if ($result = static::getCache($key)) {
            return $result;
        }
        // 调用Service方法
        $result = self::call($name, $args);
        // 是否需要转换到数组
        $result = static::toArray($result);
        // 调用静态方法后回调
        static::afterCall($name, $args, $result);
        return $result;
    }
    
    /**
     * 调用Dao方法
     * @param string $name
     * @param array  $args
     * @return mixed
     * @throws UnknownClassException
     */
    private static function call($name, $args)
    {
        $dao = self::getDao();
        if ($dao->hasMethod($name)) {
            return call_user_func_array([$dao, $name], $args);
        }
        // Dao方法不存在
        throw new UnknownMethodException($name);
    }
    
    /**
     * 数据访问层对象实例
     * @return Dao
     * @throws UnknownClassException
     */
    public static function getDao()
    {
        if (empty(self::$daoInstances[static::$modelClass])) {
            $config                                  = [
                'modelClass' => static::modelClass(),
                'baseWhere'  => static::baseWhere()
            ];
            self::$daoInstances[static::$modelClass] = new Dao($config);
        }
        return self::$daoInstances[static::$modelClass];
    }
    
    /**
     * 在调用静态方法之前回调
     * @param string $name
     * @param array  $arguments
     */
    protected static function beforeCall($name = '', $arguments = [])
    {
    }
    
    /**
     * 在调用静态方法之后回调
     * @param string $name
     * @param array  $arguments
     * @param mixed  $result
     */
    protected static function afterCall($name, $arguments, $result)
    {
    }
    
    /**
     * 根据当前调用参数返回缓存key
     * @return string
     */
    private static function getCurrentCacheKey()
    {
        return md5(json_encode([static::$modelClass, static::$currentCallArgs]));
    }
    
    /**
     * model转换到数组
     * @param array $result
     * @return array
     * @throws UnknownClassException
     */
    protected static function toArray($result)
    {
        if (static::asArray() && $result instanceof Model || is_array($result)) {
            return ArrayHelper::toArray($result);
        }
        return $result;
    }
    
    /**
     * 获取模型层类名
     * @param string $modelClass
     * @return string
     * @throws UnknownClassException
     */
    public static function modelClass($modelClass = '')
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
    
    /**
     * 查询结果转换为数组
     * @param bool $value
     * @return bool
     * @throws UnknownClassException
     */
    public static function asArray($value = true)
    {
        if (static::$asArray != $value) {
            static::$asArray = $value;
            static::getDao()->setAsArray($value);
        }
        return static::$asArray;
    }
    
    /**
     * 使用缓存
     * @param      $value
     * @param null $duration
     * @return mixed
     */
    public static function cache($value, $duration = null)
    {
        if ($value) {
            $key  = static::getCurrentCacheKey();
            $data = static::getCache($key);
            if (!$data || $duration !== null) {
                static::setCache($key, $value, $duration);
            }
        }
        return $value;
    }
    
    /**
     * 获取缓存值
     * @param string $key 缓存条件
     * @return mixed
     */
    protected static function getCache($key)
    {
        return null;
    }
    
    /**
     * 设置缓存
     * @param array $condition 缓存条件
     * @param mixed $value
     * @param mixed $duration
     * @return bool
     */
    protected static function setCache($key, $value, $duration)
    {
        return false;
    }
    
}
