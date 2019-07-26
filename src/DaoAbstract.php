<?php

namespace doyzheng\yii2dao;

use Yii;
use Exception;
use yii\base\Component;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Transaction;

/**
 * 数据访问层抽象类
 * Class Dao
 * @package doyzheng\yii2dao
 * @property ActiveRecord model
 * @property string       pk
 * @property string       defaultOrder
 * @property array        attributes
 */
abstract class DaoAbstract extends Component
{
    /**
     * @var string 模型类名
     */
    public $modelClass;
    
    /**
     * @var array 基础查询条件
     */
    public $baseWhere;
    
    /**
     * 是否将结果作为数组返回
     * @var bool
     */
    public $asArray = false;
    
    /**
     * @var bool 是否保存sql语句
     */
    public $isSaveSql = YII_DEBUG;
    
    /**
     * 保存执行过的sql
     * @var array
     */
    private $sql = [];
    
    /**
     * 全部错误信息
     * @var array
     */
    private $errors = [];
    
    /**
     * 模型字段
     * @var array
     */
    private $_attributes = [];
    
    /**
     * 数据表对应的唯一主键
     * @var string
     */
    private $_pk;
    
    /**
     * 获取model层类名
     * @return string
     */
    public function getModelClass()
    {
        return $this->modelClass;
    }
    
    /**
     * 设置model层类名
     * @param $modelClass
     */
    public function setModelClass($modelClass)
    {
        $this->modelClass = $modelClass;
    }
    
    /**
     * 获取模型主键
     * @return string
     */
    public function getPk()
    {
        if (!$this->_pk) {
            if ($keys = $this->model->primaryKey()) {
                $this->_pk = array_shift($keys);
            }
        }
        return $this->_pk;
    }
    
    /**
     * 设置模型对象
     * @param ActiveRecord $model
     */
    public function setModel(ActiveRecord $model)
    {
        $this->model = $model;
    }
    
    /**
     * 获取查询对象
     * @return ActiveQuery
     */
    public function find()
    {
        return call_user_func_array($this->modelClass . '::find', []);
    }
    
    /**
     * 获取模型对象
     * @return ActiveRecord
     */
    public function getModel()
    {
        return new $this->modelClass;
    }
    
    /**
     * 获取查询对象
     * @param array|string $where  查询条件支持数组或主键
     * @param array|string $fields 查询的字段
     * @param string|array $order  查询结果排序方式
     * @return ActiveQuery
     */
    public function getQuery($where = [], $fields = '', $order = '')
    {
        $query = $this->find();
        // 如果条件是数字类型,使用主键查询
        $where = is_numeric($where) ? [$this->pk => $where] : $where;
        $query->where($this->getWhere($where));
        // 查询的字段, 如果是数组转化成字符串
        $fields = is_array($fields) ? join(',', $fields) : $fields;
        $query->select($fields);
        // 字段使用别名时,不能返回模型对象
        $asArray = stripos($fields, ' as ') !== false;
        $query->asArray($asArray ? $asArray : $this->asArray);
        // 排序方式
        $query->orderBy($order ? $order : $this->defaultOrder);
        // 保存执行的SQL语句
        if ($this->isSaveSql) {
            $this->setSql($query->createCommand()->getRawSql());
        }
        return $query;
    }
    
    /**
     * 获取查询条件
     * @param array $where
     * @return array
     */
    public function getWhere($where)
    {
        $condition[] = 'and';
        if ($where) {
            $condition[] = $where;
        }
        if ($baseWhere = $this->getBaseWhere()) {
            $condition[] = $baseWhere;
        }
        return $condition;
    }
    
    /**
     * 获取基础查询条件
     * @return array
     */
    public function getBaseWhere()
    {
        return $this->baseWhere;
    }
    
    /**
     * 设置基础查询条件
     * @param array $where
     */
    public function setBaseWhere($where)
    {
        $this->baseWhere = $where;
    }
    
    /**
     * 获取错误信息
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /**
     * 设置错误信息
     * @param mixed $error
     */
    public function setErrors($error)
    {
        $this->errors[] = $error;
    }
    
    /**
     * 获取第一个错误信息
     * @return mixed
     */
    public function getError()
    {
        $error = $this->errors;
        return array_shift($error);
    }
    
    /**
     * 开始事务
     * @return Transaction
     */
    public function beginTransaction()
    {
        return Yii::$app->db->beginTransaction();
    }
    
    /**
     * 查询结果是否返回数组
     * @return bool
     */
    public function getAsArray()
    {
        return $this->asArray;
    }
    
    /**
     * 设置查询结果为数组
     * @param bool $asArray
     */
    public function setAsArray($asArray)
    {
        $this->asArray = $asArray;
    }
    
    /**
     * 获取当前表自增ID
     * @return int
     */
    public function getAutoIncrement()
    {
        $dbName    = $this->getDbName();
        $tableName = $this->getTableName();
        $sql       = "#
        select auto_increment as id from information_schema.tables where table_schema='{$dbName}' and table_name='{$tableName}'";
        try {
            $row = Yii::$app->db->createCommand($sql)->queryOne();
            if (isset($row['id'])) {
                return $row['id'];
            }
        } catch (Exception $e) {
        }
        return 1;
    }
    
    /**
     * 获取当前数据库名
     * @return string
     */
    public function getDbName()
    {
        $dsn = explode(';', Yii::$app->db->dsn);
        foreach ($dsn as $item) {
            if (stripos($item, 'dbname') !== false) {
                return substr($item, (strpos($item, '=') + 1));
            }
        }
        return '';
    }
    
    /**
     * 获取当前表名
     * @return string
     */
    public function getTableName()
    {
        return str_replace('`', '', Yii::$app->db->quoteSql($this->getModel()->tableName()));
    }
    
    /**
     * 获取默认排序方式
     * @return string
     */
    public function getDefaultOrder()
    {
        return "{$this->pk} DESC";
    }
    
    /**
     * 获取最后的sql语句
     * @return mixed
     */
    public function getLastSql()
    {
        if ($sql = $this->getSql()) {
            return array_pop($sql);
        }
        return '';
    }
    
    /**
     * 获取已经执行的sql
     * @return array
     */
    public function getSql()
    {
        return $this->sql;
    }
    
    /**
     * 保存执行的sql
     * @param string $sql
     */
    public function setSql($sql)
    {
        $this->sql[] = $sql;
    }
    
    /**
     * 获取模型字段
     * @return array
     */
    public function getAttributes()
    {
        if (!$this->_attributes) {
            $this->_attributes = $this->model->attributes();
        }
        return $this->_attributes;
    }
    
}
