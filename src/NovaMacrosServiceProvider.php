<?php

namespace Webard\NovaMacros;

use Closure;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Http\Requests\ActionRequest;

class NovaMacrosServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if (! Field::hasMacro('havingable')) {
            /**
             * Used to filter by number fields that have generated data by aggregator methods like withSum, withCount etc.
             * ! Currently works only with numeric data.
             */
            Field::macro(
                'havingable',
                function () {
                    /**
                     * @var Field $this
                     */
                    // @phpstan-ignore-next-line
                    return $this->filterable(fn ($request, $query, $value, $attribute) => $query->havingNovaFilterNumber($attribute, $value));
                }
            );
        }

        if (! Action::hasMacro('canSeeWithModel')) {
            /**
             * In many cases we need to check if user can see action and run it. This macro allows to do it in one line.
             */
            Action::macro(
                'canSeeWithModel',
                function (Closure $callback) {
                    /**
                     * @var Action $this
                     */
                    return $this->canSee(
                        function (NovaRequest $request) use ($callback) {
                            if ($request instanceof ActionRequest) {
                                $q = $request->findModel($request->resources);
                            } else {
                                $q = $request->findModel();
                            }

                            return ! $q->exists || $callback($request, $q);
                        }
                    );
                }
            );
        }

        if (! Action::hasMacro('canSeeAndRunWhen')) {
            Action::macro(
                'canSeeAndRunWhen',
                function (string $ability) {
                    /**
                     * @var Action $this
                     */
                    return $this->canSeeWithModel(
                        fn (NovaRequest $request, Model $model) => $request->user()->can($ability, $model)
                    )->canRun(
                        fn (NovaRequest $request, Model $model) => $request->user()->can($ability, $model)
                    );
                }
            );
        }

        if (! Action::hasMacro('canSeeAndRun')) {
            Action::macro(
                'canSeeAndRun',
                function (Closure $callback) {
                    /**
                     * @var Action $this
                     */
                    return $this
                        ->canSeeWithModel(
                            fn (NovaRequest $request, $model) => $callback($request, $model)
                        )
                        ->canRun(
                            fn (NovaRequest $request, $model) => $callback($request, $model)
                        );
                }
            );
        }

        if (! Field::hasMacro('defaultOnCreate')) {
            Field::macro(
                'defaultOnCreate',
                function ($callback) {
                    /**
                     * @var Field $this
                     */
                    return $this->default(
                        function (NovaRequest $request) use ($callback) {
                            if ($request->isCreateOrAttachRequest()) {
                                return $callback instanceof Closure
                                        ? call_user_func($callback, $request)
                                        : $callback;
                            }
                        }
                    );
                }
            );
        }

        // ? Useful for filtering Number fields that have generated data by aggregator methods like withSum, withCount etc.
        Builder::macro(
            'havingNovaFilterNumber',
            function (string $column, ?array $value) {
                /**
                 * @var EloquentBuilder $this
                 */
                if ($value === null || ($value[0] === null && $value[1] === null)) {
                    return $this;
                }
                if ($value[1] === null) {
                    $this->having($column, '>=', $value[0]);
                } elseif ($value[0] === null) {
                    $this->having($column, '<=', $value[1]);
                } else {
                    $this->havingBetween($column, $value);
                }
            }
        );
    }

    /**
     * Register the application services.
     */
    public function register()
    {
    }
}
