<?php

declare(strict_types=1);

/**
 * This file is part of the Igniter framework.
 *
 * @package    Igniter
 * @category   ActiveRecord
 * @author     Hamid Ssemitala <semix.hamidouh@gmail.com>
 * @license    https://opensource.org/licenses/MIT  MIT License
 * @link       https://igniter.com
 */
namespace Igniter\ActiveRecord\Query;

use Closure;
use Igniter\ActiveRecord\Model;
use CodeIgniter\Database\RawSql;
use Igniter\ActiveRecord\Collection;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;
use Igniter\ActiveRecord\Exceptions\ModelException;
use CodeIgniter\Database\Exceptions\DatabaseException;

class Builder
{
    protected ?string $table;
    protected bool $escapeValue = true;

    protected ?BaseBuilder $builder = null;

    public function __construct(protected ConnectionInterface $connection, protected Model $model)
    {
        $this->table = $model->getTable();
        if ($this->table) {
            $this->builder = $this->connection->table($this->table);
        }
    }

    /**
     * @param Model $model
     */
    public function setModel(Model $model)
    {
        $this->model = $model;
        return $this;
    }

    public function setTable($table)
    {
        $table = is_object($table) ? $table->getTable() : $table;

        $this->builder = $this->connection->table($table);
        return $this;
    }

    /**
     * An alias of setTable
     * 
     * @param string|array $table
     * 
     * @return Builder
     */
    public function from($table)
    {
        return $this->setTable($table);
    }

    /**
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * @return BaseBuilder
     */
    public function getBuilder(): ?BaseBuilder
    {
        return $this->builder;
    }

    public function create(array $data = []): Model|bool
    {

        if (count($data) === 0) {
            throw new ModelException('There are no valid columns found to update.');
        }

        $this->builder->insert($data);

        $lastInsertID = $this->connection?->insertID();
        if ($lastInsertID === false) {
            throw new DatabaseException('Failed to insert data into the database.');
        }

        if ($lastInsertID) {

            $this->model->{$this->model->getPrimaryKey()} = $lastInsertID;

            return $this->model;
        }

        return false;
    }

    public function firstOrNew(array $attributes = []): Model
    {
        foreach($attributes as $attribute => $value){
			$this->where($attribute, $value);
		}

        if (!is_null($instance = $this->first())) {
            return $instance;
        }

        return $this->getModel()->newInstance($attributes);
    }

    public function update(array $data = [])
    {
        if (count($data) === 0) {
            throw new ModelException('There are no valid columns found to update.');
        }

        $this->builder->where($this->model->getPrimaryKey(), $this->model->{$this->model->getPrimaryKey()})->update($data);

        return $this->model;
    }

    protected function processValue(mixed $value, string|Closure $column): mixed
    {
        // if ($value instanceof \Closure) {
        //     return $this->whereClosure($value, $column);
        // }

        if (is_string($value) && $this->escapeValue) {
            return $this->connection->escape($value);
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (is_array($value)) {

            $value = array_map(function ($value) use ($column) {
                return $this->processValue($value, $column);
            }, $value);
            return "(" . implode(',', $value) . ")";
        }

        return $value;
    }

    protected function whereClosure(Closure $closure, $column)
    {
        extract($this->processKey($column));
        
        $model = clone $this->getModel();
        $newQuery = $model->newQueryBuilder();
        
        call_user_func($closure, $newQuery);

        return $this->subQuery($model);
    }

    public function subQuery(Model $model)
    {
        $this->escapeValue = false;
        $subQuery = $model->newQueryBuilder()->getBuilder()->getCompiledSelect();
        $subQuery = '(' . $subQuery . ')';
        return $subQuery;
    }

    protected function processKey(string|Closure $column): array
    {
        if (is_string($column)) {
            $columnAndField = [];
            $columnField = explode('.', $column);
            if (count($columnField) > 1) {
                $columnAndField['field'] = $columnField[1];
                $columnAndField['table'] = $columnField[0];
            } else {
                $columnAndField['field'] = $columnField[0];
                $columnAndField['table'] = null;
            }
            
            return $columnAndField;
        }

        return [
            'field' => $column,
            'table' => null,
        ];
    }

    protected function operatorClosure(Closure $closure, $column)
    {
        return $this->whereClosure($closure, $column);
    }

    protected function keyClosure(Closure $closure)
    {
        $this->builder->groupStart();
        call_user_func($closure, $this);
        $this->builder->groupEnd();
    }

    public function where(mixed $key, string|int|Closure|null $operator = null, mixed $value = null)
    {
        if (func_num_args() === 2) {
            if ($operator instanceof Closure) {
                $this->escapeValue = false;
                $operator = $this->operatorClosure($operator, $key);
                extract($this->processKey($key));
                $key = $field;
            }
            $value = $operator;
            $operator = '=';
        }

        if ($value instanceof Closure) {
            $this->escapeValue = false;
            $value = $this->whereClosure($value, $key);
            extract($this->processKey($key));
            $key = $field;
        }

        $value = $this->processValue($value, $key);

        if ($key instanceof Closure) {
            $this->keyClosure($key);
            return $this;
        } else {
            $this->builder->where($key . " " . $operator . " " . $value);
            return $this;
        }

    }

    public function whereIn(string $key, string|array|Closure $values): self
    {
        if (is_array($values)) {
            if (count($values) == 0)
                $values[] = 0;
        }
        return $this->where($key, 'IN', $values);
    }

    public function whereNotIn(string $key, string|array|Closure $values): self
    {
        if (is_array($values)) {
            if (count($values) == 0)
                $values[] = 0;
        }
        return $this->where($key, 'NOT IN', $values);
    }

    public function join(string|array|Model $table, string $key, string $operator = '=', ?string $value = null, $type = 'inner')
    {
        if ($table instanceof Model) {
            $table = $table->getTable();
        }
            
        if ($value) {
            $this->builder->join($table, $key . " " . $operator . " " . $value, $type);
        } else {
            $this->builder->join($table, $key);
        }

        return $this;
    }

    // public function join(string $table, string $key, string $operator = '=', ?string $value = null): self
    // {
    //     if ($value) {
    //         $this->builder->join($table, $key . " " . $operator . " " . $value);
    //     } else {
    //         $this->builder->join($table, $key);
    //     }
    //     return $this;
    // }


        /**
     * Adds new LEFT JOIN statement to the current query.
     *
     * @param string|Raw|Closure|array $table
     * @param string|Raw|Closure $key
     * @param string $operator
     * @param string|Raw|Closure|null $value
     *
     * @return static
     * @throws Exception
     */
    public function leftJoin(string|array|Model $table, string $key, string $operator = '=', ?string $value = null)
    {
        return $this->Join($table, $key, $operator, $value, 'left');
    }

    /**
     * Adds new RIGHT JOIN statement to the current query.
     *
     * @param string|RawSql|Closure|array $table
     * @param string|RawSql|Closure $key
     * @param string $operator
     * @param string|RawSql|Closure|null $value
     *
     * @return static
     * @throws Exception
     */
    public function rightJoin(string|array|Model $table, string $key, string $operator = '=', ?string $value = null)
    {
        return $this->Join($table, $key, $operator, $value, 'right');
    }

    public function raw($value, array $bindings = [])
    {
        return new RawSql($value);
    }

    public function get(array $columns = [])
    {
        return $this->all($columns);
    }

    /**
     * Return all results or the queried ones
     * 
     * @param array|null $columns
     * 
     * @return Collection
     */
    public function all(array $columns = [])
    {
        $models = $this->getAll($columns);
        return $models;
    }

    /**
     * Query the database and return the results as model instances
     * 
     * @param array|null $columns
     * 
     * @return Collection
     */
    public function getAll(array $columns = [])
    {
        // Dispatch a "selecting" event
        
        // $this->deletable();
        
        $models = $this->builder->select($columns)->get()->getResultArray(); 
        
        $results = $this->makeModels($models);
        
        // Dispatch a "retrieved" event

        return $results;
    }

    protected function makeModels(array $models): Collection
    {
        $result = [];
        foreach ($models as $item) {
            $model = $this->getModel()->newFromQuery($item);
            $result[] = $model;
        }
        return new Collection($result);
    }

    public function find(string|int|array $ids, ?array $columns = null): Model|Collection|null
    {
        if ($columns) {
            $this->select($columns);
        }

        if (!is_array($ids)) {
            $this->where($this->model->getPrimaryKey(), $ids);
            $item = $this->builder->limit(1)->get()->getRow();

            if ($item !== null) {

                $model = $this->getModel()->newFromQuery($item);
                return $model;
            }
        } else {
            $items = $this->whereIn($this->model->getPrimaryKey(), $ids)->get();
            return $items;
        }
        
        return null;
    }

    public function first(array $columns = []): ?Model
    {
        // Dispatch a "selecting" event
        
        // $this->deletable();
        $item = $this->builder;
        if ($columns) {
            if (is_array($columns) === false) {
                $columns = func_get_args();
            }
            $item = $item->select($columns);
        } 

        $item = $item->limit(1)->get()->getRow();

        if ($item !== null) {
            
            $model = $this->getModel()->newFromQuery($item);
            // $model->setQuery($this);
            // Dispatch a "retrieved" event
            return $model;
        }
        return $item;
    }

    // public function toSql()
    // {
    //     if (!is_null($this->builder->getCompiledDelete())) {
    //         return $this->builder->getCompiledDelete();
    //     }

    //     if (!is_null($this->builder->getCompiledInsert())) {    
    //         return $this->builder->getCompiledInsert();
    //     }

    //     if (!is_null($this->builder->getCompiledUpdate())) {
    //         return $this->builder->getCompiledUpdate();
    //     }

    //     return $this->builder->getCompiledSelect();
    // }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return BaseBuilder
     */
    public function __call($method, $parameters)
    {
        if ($this->builder) {
            /** @var BaseBuilder $builder */
            $builder = call_user_func_array([$this->builder, $method], $parameters);

            return $builder;
        }
        
    }

    public function __sleep()
    {
        return ['model'];
    }

    /**
     * @throws Exception
     */
    public function __wakeup()
    {
        $this->builder = $this->connection->table($this->model->getTable());
    }

    public function __destruct()
    {
        $this->builder = null;
    }

    public function __clone()
    {
        $this->builder = clone $this->builder;
    }
    
}