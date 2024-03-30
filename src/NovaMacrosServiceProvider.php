<?php

namespace Webard\NovaMacros;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\ActionRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Laravel\Nova\Resource;

class NovaMacrosServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if (! Field::hasMacro('apply')) {
            // https://github.com/nova-kit/nova-field-mixins
            Field::macro('apply', function ($mixin, ...$parameters) {
                /** @var Field $this */
                /** @var class-string|callable $mixin */
                if (\is_string($mixin) && class_exists($mixin)) {
                    $mixin = app($mixin);
                }

                if (! \is_callable($mixin)) {
                    throw new InvalidArgumentException('Unable to mixin non-callable $mixin');
                }

                $mixin($this, ...$parameters);

                return $this;
            });
        }

        if (! Field::hasMacro('capitalizeFirst')) {
            Field::macro('capitalizeFirst', function () {
                /** @var Field $this */
                return $this->displayUsing(fn ($value) => Str::ucfirst($value));
            });
        }

        if (! Field::hasMacro('helpError')) {
            Field::macro('helpError', function ($message) {
                /** @var Field $this */
                return $this->help("<span class='text-base text-red-500'>{$message}</span>");
            });
        }

        if (! Field::hasMacro('helpWarning')) {
            Field::macro('helpWarning', function ($message) {
                /** @var Field $this */
                return $this->help("<span class='text-base text-yellow-600'>{$message}</span>");
            });
        }

        if (! Field::hasMacro('helpInfo')) {
            Field::macro('helpInfo', function ($message) {
                /** @var Field $this */
                return $this->help("<span class='text-base text-primary-500'>{$message}</span>");
            });
        }

        if (! Field::hasMacro('canEditWhen')) {
            /**
             * @param  class-string<resource>|resource  $resource
             */
            Field::macro('canEditWhen', function ($ability, string|Resource $resource) {
                /** @var Field $this */
                // @phpstan-ignore-next-line find a way to fix this
                $classBasename = (string) \class_basename($resource::$model);
                $permission = $ability.\ucfirst($classBasename);

                return $this->readonly(fn ($request) => ! Nova::user($request)?->can($permission, $resource) ?: false);
            });
        }

        if (! Field::hasMacro('canViewWhen')) {
            Field::macro('canViewWhen', function ($ability, string $resource) {
                /** @var Field $this */
                $classBasename = (string) \class_basename($resource::$model);
                $permission = $ability.\ucfirst($classBasename);

                return $this->canSeeWhen($permission, $resource);
            });
        }

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
