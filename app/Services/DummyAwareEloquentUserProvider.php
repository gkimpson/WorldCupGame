<?php

namespace App\Services;

use App\Models\Scopes\ExcludeDummyUsersScope;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;

class DummyAwareEloquentUserProvider extends EloquentUserProvider
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel|null  $model
     * @return Builder<TModel>
     */
    protected function newModelQuery($model = null): Builder
    {
        return parent::newModelQuery($model)
            ->withoutGlobalScope(ExcludeDummyUsersScope::class);
    }
}
