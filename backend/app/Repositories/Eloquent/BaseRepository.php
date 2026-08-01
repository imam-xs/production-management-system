<?php

namespace App\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared CRUD for the Eloquent repositories.
 *
 * Deliberately minimal — it holds only the operations that are genuinely
 * identical for every model. Anything involving a WHERE clause, a lock or a join
 * belongs in the concrete repository, where it can be named after the business
 * question it answers.
 *
 * @template TModel of Model
 */
abstract class BaseRepository
{
    /**
     * A fresh instance of the managed model, used purely as a query factory.
     *
     * Returning the instance rather than a class-string is what lets static
     * analysis carry TModel through to the builder, so `query()` is typed as
     * `Builder<Item>` in ItemRepository rather than `Builder<Model>`.
     *
     * @return TModel
     */
    abstract protected function model(): Model;

    /**
     * @return Builder<TModel>
     */
    protected function query(): Builder
    {
        // Model::newQuery() is annotated `Builder<static>`, and static analysis
        // resolves `static` to the template bound (Model) rather than to TModel.
        // The narrowing annotation restores the concrete type for callers; the
        // runtime type was always correct.
        /** @var Builder<TModel> $query */
        $query = $this->model()->newQuery();

        return $query;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    protected function persist(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    protected function applyUpdate(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model->refresh();
    }
}
