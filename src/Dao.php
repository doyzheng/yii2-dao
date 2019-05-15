<?php

namespace doyzheng\yii2dao;

use Exception;
use Throwable;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

/**
 * Class Dao
 * @package app\tests
 */
class Dao extends DaoAbstract
{
    
    /**
     * 获取单条数据
     * @param array  $where
     * @param string $fields
     * @param mixed  $order
     * @return ActiveRecord
     */
    public function get($where = [], $fields = '', $order = '')
    {
        // 如果条件是数字,默认使用主键查询
        $where = is_numeric($where) ? [$this->pk => $where] : $where;
        return $this->getQuery($where, $fields, $order)->one();
    }
    
    /**
     * 获取全部数据
     * @param array  $where
     * @param string $fields
     * @param string $order
     * @return ActiveRecord[]
     */
    public function getAll($where = [], $fields = '', $order = '')
    {
        return $this->getQuery($where, $fields, $order)->all();
    }
    
    /**
     * 分页查询
     * @param array  $where
     * @param string $page
     * @param string $limit
     * @param string $fields
     * @param string $order
     * @return ActiveRecord[]
     */
    public function getPage($where = [], $page = '1', $limit = '', $fields = '', $order = '')
    {
        $page   = $page < 1 ? 1 : $page;
        $limit  = $limit < 1 ? 10 : $limit;
        $offset = ($page - 1) * $limit;
        return $this->getQuery($where, $fields, $order)->offset($offset)->limit($limit)->all();
    }
    
    /**
     * 使用模型类逐条添加
     * @param $data
     * @return array
     */
    public function addAll($data)
    {
        $tr  = $this->beginTransaction();
        $ids = [];
        try {
            foreach ($data as $key => $item) {
                $id = $this->add($item);
                if (!$id) {
                    $tr->rollBack();
                    return $ids;
                }
                $ids[$key] = $id;
            }
            $tr->commit();
            return $ids;
        } catch (Exception $e) {
            $tr->rollBack();
            $this->setErrors($e->getMessage());
        }
        return $ids;
    }
    
    /**
     * 添加数据
     * @param array $data
     * @return int
     */
    public function add($data)
    {
        $model = $this->getModel();
        $model->setAttributes($data);
        if ($model->save()) {
            return $model[$this->pk];
        }
        $this->setErrors($model->getErrors());
        return 0;
    }
    
    /**
     * 批量插入数据
     * @param array $data
     * @return int 返回总插入条数
     */
    public function batchInsert($data)
    {
        // 分组批量添加,每次最多1000条
        $data      = array_chunk($data, 1000);
        $tableName = $this->getModel()->tableName();
        $rows      = 0;
        $tr        = $this->beginTransaction();
        try {
            foreach ($data as $item) {
                $row = $this->_batchInsert($tableName, $item);
                if (!$row) {
                    $tr->rollBack();
                    return 0;
                }
                $rows += $row;
            }
            $tr->commit();
            return $rows;
        } catch (Exception $e) {
            $tr->rollBack();
            $this->setErrors($e->getMessage());
        }
        return $rows;
    }
    
    /**
     * 批量插入(内部)
     * @param $table
     * @param $data
     * @return int
     * @throws \yii\db\Exception
     */
    private function _batchInsert($table, $data)
    {
        $columns = [];
        $rows    = [];
        foreach ($data as $item) {
            // 去掉不存在的字段
            $item = ArrayHelper::filter($item, $this->getAttributes());
            if (!$columns) {
                $columns = array_keys($item);
            }
            $rows[] = array_values($item);
        }
        // 设置批量插入数据需要的参数
        $query = $this->find()->createCommand()->batchInsert($table, $columns, $rows);
        // 保存sql语句
        $this->setSql($query->getRawSql());
        return $query->execute();
    }
    
    /**
     * 单条更新
     * @param array|ActiveRecord $where
     * @param array              $data
     * @return bool
     */
    public function update($where, $data)
    {
        $model = $this->getQuery($where)->asArray(false)->one();
        if ($model) {
            $model->setAttributes($data);
            if ($model->save()) {
                return true;
            }
            $this->setErrors($model->getErrors());
        }
        return false;
    }
    
    /**
     *  使用模型类逐条更新
     * @param array $where
     * @param array $data
     * @return bool
     */
    public function updateAll($where, $data)
    {
        $models = $this->getQuery($where)->asArray(false)->all();
        if ($models) {
            $tr = $this->beginTransaction();
            try {
                foreach ($models as $model) {
                    $model->setAttributes($data);
                    if (!$model->save()) {
                        $tr->rollBack();
                        $this->setErrors($model->getErrors());
                        return false;
                    }
                }
                $tr->commit();
                return true;
            } catch (Exception $e) {
                $tr->rollBack();
                $this->setErrors($e->getMessage());
            }
        }
        return false;
    }
    
    /**
     * 删除单条记录
     * @param array $where
     * @return bool
     */
    public function delete($where)
    {
        $model = $this->getQuery($where, $this->pk)->asArray(false)->one();
        if ($model) {
            try {
                if (!$model->delete()) {
                    $this->setErrors($model->getErrors());
                    return false;
                }
                return true;
            } catch (Throwable $e) {
                $this->setErrors($e->getMessage());
            }
        }
        return false;
    }
    
    /**
     * 批量删除
     * @param array $where
     * @param array $params
     * @return bool
     */
    public function deleteAll($where, $params = [])
    {
        try {
            return $this->getModel()->deleteAll($where, $params);
        } catch (Throwable $e) {
            $this->setErrors($e->getMessage());
        }
        return false;
    }
    
    /**
     * 统计符合条件记录数量
     * @param array  $where
     * @param string $fields
     * @return int
     */
    public function count($where = [], $fields = '*')
    {
        return (int)$this->getQuery($where)->asArray()->count($fields);
    }
    
    /**
     * 返回指定列值的和
     * @param array  $where
     * @param string $fields
     * @return int
     */
    public function sum($where, $fields)
    {
        return (int)$this->getQuery($where)->asArray()->sum($fields);
    }
    
    /**
     * 返回指定列值的最小值
     * @param array  $where
     * @param string $field
     * @return int
     */
    public function min($where, $field)
    {
        return (int)$this->getQuery($where)->asArray()->min($field);
    }
    
    /**
     * 返回指定列值的最大值
     * @param array  $where
     * @param string $field
     * @return int
     */
    public function max($where, $field)
    {
        return (int)$this->getQuery($where)->asArray()->max($field);
    }
    
    /**
     * 字段值增长
     * @param array        $where 条件
     * @param string|array $field 字段名
     * @param int          $step  增长值
     * @return bool
     */
    public function inc($where, $field, $step = 1)
    {
        $fields = is_string($field) ? explode(',', $field) : $field;
        $upData = [];
        foreach ($fields as $field => $val) {
            if (is_numeric($field)) {
                $field = $val;
            } else {
                $step = $val;
            }
            $upData[$field] = $this->raw("$field + $step");
        }
        return boolval($this->model->updateAll($upData, $where));
    }
    
    /**
     * 使用表达式设置数据
     * @access public
     * @param mixed $value 表达式
     * @return Expression
     */
    public function raw($value)
    {
        return new Expression($value);
    }
    
    /**
     * 字段值减少
     * @param array        $where 条件
     * @param string|array $field 字段名
     * @param int          $step  增长值
     * @return bool
     */
    public function dec($where, $field, $step = 1)
    {
        $fields = is_string($field) ? explode(',', $field) : $field;
        $upData = [];
        foreach ($fields as $field => $val) {
            if (is_numeric($field)) {
                $field = $val;
            } else {
                $step = $val;
            }
            $upData[$field] = $this->raw("$field - $step");
        }
        return boolval($this->model->updateAll($upData, $where));
    }
    
}
