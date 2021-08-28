<?php

namespace App\Services;

use App\Models\Model;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Schema;

abstract class Service
{
    /**
     * The model to be used for this service.
     *
     * @var \App\Models\Model
     */
    protected $model;

    /**
     * Show the resource with all its relations
     *
     * @var bool
     */
    protected $showWithRelations = false;


    /**
     * Default pagination to use for item listings
     *
     * @var bool
     */
    protected $pagination = 20;


    /**
     * Default ordering
     *
     * @var bool
     */
    protected $ranking = 'DESC';

    /**
     * Specifies service relations
     *
     * @var array
     */
    protected $relations = [];


    /**
     * Constructor: Initializes the relations array with model relations
     *
     * @return void
     */
    public function __construct()
    {
        $this->relations = $this->model() ? $this->model()->getRelations() : [];
    }

    /**
     * Get a listing of resource matching with query params appplied
     *
     * @param array $params
     * @return \Illuminate\Support\Collection
     */
    public function all(array $params = []): Collection
    {
        $query = $this->model()->query()
            ->when($this->showWithRelations(), function ($query) {
                $query->with($this->getRelations());
            })
            ->when(Arr::get($params, 'order_by_field'), function ($query) use ($params) {
                $query->orderBy(Arr::get($params, 'order_by_field'), $this->getOrderDirection(Arr::get($params, 'order_by_direction')));
            })
            ->when(Arr::get($params, 'with_trashed'), function ($query) use ($params) {
                $query->withTrashed();
            });

        $query = $this->applyFilters($query, $params);

        return $this->shouldPaginate(Arr::get($params, 'paginate'))
            ? $query->paginate($this->getPerPage(Arr::get($params, 'per_page')))
            : $query->get();
    }

    /**
     * Store a new resource with the provided data.
     *
     * @param array $data
     * @return \App\Models\Model|null
     */
    public function store(array $data = []): ?Model
    {
        $data = $this->getPreparedSaveData($data);

        if (count($data) < 1) {
            return null;
        }

        $resource = $this->model()->fill($data);
        $resource->save();

        return $resource;
    }

    /**
     * Show the specified resource. Load it with or without its relations
     * depending on the value of the showWithRelations variable.
     *
     * @param int|string $id
     * @return \App\Models\Model|null
     */
    public function show($id): ?Model
    {
        $resource = $this->find($id);

        if (!$resource) {
            return null;
        }

        return $this->showWithRelations() ? $resource->load($this->relations) : $resource;
    }

    /**
     * Update the specified resource with the specified data.
     * Returns null if the resource was not found or the data is not valid.
     *
     * @param \App\Models\Model $resource
     * @param array $data
     * @return \App\Models\Model|null
     */
    public function update(Model $resource, array $data = []): Model
    {
        $data = $this->getPreparedUpdateData($data, $resource);

        if (count($data)) {
            $resource->update($data);
        }

        return $resource;
    }

    /**
     * Delete the specified resource.
     *
     * @param \App\Models\Model $resource
     * @return bool
     */
    public function delete(Model $resource): bool
    {
        return $resource->delete();
    }

    /**
     * Find a resource in the model using the specified
     * value and column for defining the constraints.
     *
     * @param mixed $value
     * @param string|null $column
     * @param bool $withTrashed
     * @return \App\Models\Model|null
     */
    public function find($value, ?string $column = null, bool $withTrashed = false): ?Model
    {
        $column = $column ?? $this->model::getPrimaryKey();

        if (!$this->tableHasColumn($column)) {
            return null;
        }

        return $this->model()->query()
            ->when($withTrashed, function ($query) {
                $query->withTrashed();
            })
            ->where($column, $value)
            ->first();
    }

    /**
     * Get a new instance of the model used by this service.
     *
     * @return \App\Models\Model|null
     */
    public function model(): ?Model
    {
        return $this->model ? new $this->model : null;
    }

    /**
     * Is the service set to load resources with their relations?
     *
     * @return bool
     */
    public function showWithRelations(): bool
    {
        return $this->showWithRelations;
    }
    /**
     * Get the final data that should be used in creating a new resource
     *
     * @param array $data
     * @return array
     */
    public function getPreparedSaveData(array $data): array
    {
        return $this->getValidData($data);
    }

    /**
     * Get the final data that should be used in updating a resource
     *
     * @param array $data
     * @param \App\Models\Model $resource
     * @return array
     */
    public function getPreparedUpdateData(array $data, Model $resource): array
    {
        return $this->getValidData($data);
    }

    /**
     * Search results of the query for a keyword.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $keyword
     * @param array $columns
     * @return \Illuminate\Database\Query\Builder $query Updated query
     */
    public function search(Builder $query, string $keyword, array $columns = []): Builder
    {
        $terms = static::getSearchTermParts($keyword);
        $columns = count($columns) ? $columns : $this->model()->getSearchFields();

        if (empty($terms)) {
            return $query;
        }

        return $query->where(function ($query) use ($terms, $columns) {
            foreach ($columns as $column) {

                $query->orWhere(function ($query) use ($terms, $column) {
                    foreach ($terms as $term) {
                        $query->where($field, 'LIKE', '%' . $term . '%');
                    }
                });

            }
        });
    }

    /**
     * Do further querying on the current query object
     * Will be overriden by service classes with more complicated filtering requirements
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $data
     * @return \Illuminate\Database\Query\Builder
     */
    public function applyFilters(Builder $query, array $data): Builder
    {
        // Keyword Search
        if (isset($data['keyword']) && $data['keyword']) {
            $query = $this->search($query, $data['keyword']);
        }

        return $query;
    }

    /**
     * Enables the service to fetch data with relations
     *
     * @param array $relations
     * @return \App\Service\Service
     */
    public function enableWithRelationships(array $relations = []): self
    {
        if (count($relations)) {
            $this->relations = $relations;
        }

        $this->showWithRelations = true;

        return $this;
    }


    /**
     * Disables service to fetch resource with relations and resets the relations to model's relations
     *
     * @return \App\Service\Service
     */
    public function disableWithRelationships(): self
    {
        $this->relations = $this->model() ? $this->model()->getRelations() : [];
        $this->showWithRelations = false;

        return $this;
    }

    /**
     * Set relations that can be loaded.
     *
     * @param array $relations
     * @return void
     */
    public function setRelations(array $relations): void
    {
        $this->relations = $relations;
    }

    /**
     * Get the current relations we are working with
     *
     * @return array
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Get the valid data fields from the specified data array.
     * Do this by checking if the field exists in the table.
     *
     * @param array $data
     * @return array
     */
    protected function getValidData(array $data = []): array
    {
        $validData = [];

        if (!count($data)) {
            return $validData;
        }

        foreach ($data as $key => $value) {
            if ($this->tableHasColumn($key)) {
                $validData[$key] = $value;
            }
        }

        return $validData;
    }

    /**
     * Get the ranking to be used when ordering resources.
     * Either ascending or descending order.
     *
     * @param string|null $direction
     * @return string
     */
    protected function getOrderDirection(?string $direction): string
    {
        return strtolower($ranking) === 'DESC' ? 'DESC' : 'ASC';
    }

    /**
     * Check if the resources should be paginated.
     *
     * @param bool|string $paginate
     * @return bool
     */
    protected function shouldPaginate($paginate = false): bool
    {
        return (is_string($paginate) && strtolower($paginate) === 'true') || (bool) $paginate;
    }

    /**
     * Get the per page value from the specified value.
     *
     * @param int|string $per_page
     * @return int
     */
    protected function getPerPage($per_page = 0): int
    {
        $per_page = intval($per_page);

        return is_int($per_page) ? $per_page : 20;
    }

    /**
     * Check if the model has the specified column in its table.
     *
     * @param string $column
     * @return bool
     */
    protected function tableHasColumn(string $column): bool
    {
        $table = $this->model()->getTable();

        return $column && Schema::hasColumn($table, $column);
    }

    /**
     * Get the atomic words from a search term.
     * Truncate contiguous spaces to only a single space for explode to work desirably
     *
     * @param string $term
     * @return array
     */
    private static function getSearchTermParts(string $term): array
    {
        return explode(' ', preg_replace('/\s+/', ' ', trim($term)));
    }
}
