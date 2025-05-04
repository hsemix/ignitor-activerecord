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
use CodeIgniter\Database\BaseResult;
use Igniter\ActiveRecord\Collection;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;
use Igniter\ActiveRecord\Exceptions\ModelException;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Igniter\ActiveRecord\Exceptions\ModelNotFoundException;

class Builder
{
    protected array $boot = [];
    protected ?string $table;
    protected bool $escapeValue = true;
    protected bool $isRaw = false;
    protected bool $withTrashed = false;
    protected bool $onlyTrashed = false;

    protected ?BaseResult $rawResult = null;
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

    public function whereNull(string $key)
    {
        return $this->where("{$key} IS NULL");
    }

    public function whereNotNull(string $key)
    {
        return $this->where("{$key} IS NOT NULL");
    }

    public function limit(int $value, ?int $offset = null)
    {
        $this->builder->limit($value, $offset);

        return $this;
    }

    public function skip(int $skip)
    {
        $this->builder->offSet($skip);

        return $this;
    }

    public function take(int $amount)
    {
        return $this->limit($amount);
    }

    public function offset(int $offset)
    {
        return $this->skip($offset);
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

    /**
     * Adds a GROUP BY clause to the query for the specified field.
     *
     * @param string $field The name of the field to group by.
     * @return $this Returns the current instance for method chaining.
     */
    public function groupBy(string $field)
    {
        $this->builder->groupBy($field);

        return $this;
    }

    public function orderBy(string $fields, string $defaultDirection = 'ASC')
    {
        $this->builder->orderBy($fields, $defaultDirection);

        return $this;
    }

    // Aggregate Functions

    public function count(string $field = '*', string $fieldAlias = 'count')
    {
        $this->deletable();
        $this->builder->select($this->raw('COUNT(' . $field . ') AS ' . $fieldAlias));
        $result = $this->builder->get()->getRow();
        return (int)$result->{$fieldAlias};
    }

    public function avg($field, string $fieldAlias = 'avg')
    {
        $this->deletable();
        $result = $this->builder->select($this->raw('AVG(' . $field . ') AS ' . $fieldAlias));
        $result = $this->builder->get()->getRow();
        return (int)$result->{$fieldAlias};
    }

    public function max(string $field, string $fieldAlias = 'max')
    {
        $this->deletable();
        $result = $this->builder->select($this->raw('MAX(' . $field . ') AS ' . $fieldAlias));
        $result = $this->builder->get()->getRow();
        return (int)$result->{$fieldAlias};
    }

    public function min(string $field, string $fieldAlias = 'min')  
    {
        $this->deletable();
        $result = $this->builder->select($this->raw('MIN(' . $field . ') AS ' . $fieldAlias));
        $result = $this->builder->get()->getRow();
        return (int)$result->{$fieldAlias};
    }

    public function sum(string $field, string $fieldAlias = 'sum')
    {
        $this->deletable();
        $result = $this->builder->select($this->raw('SUM(' . $field . ') AS ' . $fieldAlias));
        $result = $this->builder->get()->getRow();
        return (int)$result->{$fieldAlias};
    }

    protected function checkTableField(string $table, string $field): bool
    {
        if ($this->connection->fieldExists($field, $table)) {
            return true;
        }

        return false;
    }

    protected function deletable()
    {
        if ($this->checkTableField($this->getModel()->getTable(), $this->getModel()->getDeleteKey())) {
            if ($this->withTrashed) {
				
			} elseif ($this->onlyTrashed) {
				$this->whereNotNull($this->getModel()->getDeleteKey());
			} else {
				$this->whereNull($this->getModel()->getDeleteKey());
			}
        }
        return $this;
    }

    /**
     * Return all results including trashed ones
     * 
     * @param null
     * 
     * @return static
     */
    public function withTrashed()
    {
		$this->withTrashed = true;
		return $this;
	}

    /**
     * Return only Trashaed records i.e with deleted_at not null
     * 
     * @param null
     * 
     * @return static
     */
    public function onlyTrashed()
    {
		$this->onlyTrashed = true;
		return $this;
	}

    public function setRawQuery(string $query, array $bindings = [])
    {
        $this->isRaw = true;

        if (count($bindings) > 0) {
            $this->rawResult = $this->connection->query($query, $bindings);
        } else {
            $this->rawResult = $this->connection->query($query);
        }

        return $this;
    }
    // {
    //     $this->connection->query($query);
    //     return $this;
    // }

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
        
        $this->deletable();
        
        $models = $this->builder->select($columns)->get()->getResultArray(); 

        if ($this->isRaw) {
            $models = $this->rawResult->getResultArray();
        }
        
        $results = $this->makeModels($models, $this->boot);
        
        // Dispatch a "retrieved" event

        return $results;
    }

    protected function makeModels(array $models, array $bootable = []): Collection
    {
        $result = [];
        foreach ($models as $item) {
            $model = $this->getModel()->newFromQuery($item, $bootable);
            $result[] = $model;
        }
        return new Collection($result);
    }

    public function find(string|int|array $ids, ?array $columns = null): Model|Collection|null
    {
        if ($columns) {
            $this->select($columns);
        }

        $this->deletable();

        if (!is_array($ids)) {
            $this->where($this->model->getPrimaryKey(), $ids);
            $item = $this->builder->limit(1)->get()->getRow();

            if ($item !== null) {

                $model = $this->getModel()->newFromQuery($item, $this->boot);
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
        
        $this->deletable();
        if ($this->isRaw) {
            return $this->getModel()->newFromQuery($this->rawResult->getRow(), $this->boot);
        }
        
        $item = $this->builder;
        if ($columns) {
            if (is_array($columns) === false) {
                $columns = func_get_args();
            }
            $item = $item->select($columns);
        } 

        $item = $item->limit(1)->get()->getRow();

        if ($item !== null) {
            
            $model = $this->getModel()->newFromQuery($item, $this->boot);
            // $model->setQuery($this);
            // Dispatch a "retrieved" event
            return $model;
        }
        return $item;
    }

    /**
     * Execute the query and get the first result or call a callback.
     *
     * @param array  $columns
     * @param \Closure|null  $callback
     * @return mixed
     */
    public function firstOr(array $columns = ['*'], ?Closure $callback = null)
    {
        if ($columns instanceof Closure) {
            $callback = $columns;

            $columns = ['*'];
        }

        if (!is_null($model = $this->first($columns))) {
            return $model;
        }

        return call_user_func($callback);
    }

    public function firstOrFail(array $columns = ['*'])
    {
        $item = $this->first($columns);
        if ($item === null) {
            throw new ModelNotFoundException(get_class($this->model) . ' was not found');
        }

        return $item;
    }

    /**
     * Format results and include whatever the user whats to be included in the results before being returned, 
     * It could be a relation or a closure (computed field) or just a raw query or a string
     * 
     * @param array ...$relations
     * 
     * @return static
     */
    public function with($relations)
    {
        $bootable = [];
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        foreach ($relations as $key => $value) {
            if (is_string($value) === true && is_numeric($key) === true) {
                $bootable[$value] = $value;
            } else {
                $bootable[$key] = $value;
            }
        }

        $this->boot = $this->getModel()->bootable = $bootable;

        return $this;
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
