<?php

/**
 * @copyright  Copyright (c) 2024 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\Redis;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

use function OpenTelemetry\Instrumentation\hook;

/**
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class RedisInstrumentation
{
    public const NAME = 'redis';

    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation('io.opentelemetry.contrib.php.redis');
        $attributeTracker = new RedisAttributeTracker();

        $genericPostHook = static function (\Redis $redis, array $params, mixed $ret, ?Throwable $exception) {
            self::end($exception);
        };

        hook(
            \Redis::class,
            '__construct',
            pre: static function (
                \Redis $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder(
                    $instrumentation,
                    'Redis::__construct',
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if (isset($params[0]) && is_array($params[0]) && array_key_exists('host', $params[0])) {
                    $options = $params[0];
                    $builder->setAttribute(TraceAttributes::SERVER_ADDRESS, $options['host']);
                    if (!str_starts_with($options['host'], 'unix:') && !str_contains($options['host'], '/')) {
                        $builder->setAttribute(TraceAttributes::NETWORK_TRANSPORT, 'tcp');
                        $builder->setAttribute(TraceAttributes::SERVER_PORT, $options['port'] ?? 6379);
                    } else {
                        $builder->setAttribute(TraceAttributes::NETWORK_TRANSPORT, 'unix');
                    }
                    if (array_key_exists('auth', $options) && is_array($auth = $options['auth']) && count($auth) > 1) {
                        $builder->setAttribute(TraceAttributes::DB_USER, $auth[0]);
                    }
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (\Redis $redis, array $params, mixed $ret, ?Throwable $exception) use (
                $attributeTracker,
            ) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());

                $attributes = $attributeTracker->trackRedisAttributes($redis);
                $span->setAttributes($attributes);

                self::end($exception);
            },
        );
        hook(
            \Redis::class,
            'connect',
            pre: static function (
                \Redis $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder($instrumentation, 'Redis::connect', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if ($class === \Redis::class) {
                    $builder->setAttribute(TraceAttributes::SERVER_ADDRESS, $params[0]);
                    if (!str_starts_with($params[0], 'unix:') && !str_contains($params[0], '/')) {
                        $builder->setAttribute(TraceAttributes::NETWORK_TRANSPORT, 'tcp');
                        $builder->setAttribute(TraceAttributes::SERVER_PORT, $params[1] ?? 6379);
                    } else {
                        $builder->setAttribute(TraceAttributes::NETWORK_TRANSPORT, 'unix');
                    }
                    if (
                        isset($params[6])
                        && array_key_exists('auth', $params[6])
                        && is_array($auth = $params[6]['auth'])
                        && count($auth) > 1
                    ) {
                        $builder->setAttribute(TraceAttributes::DB_USER, $auth[0]);
                    }
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (\Redis $redis, array $params, mixed $ret, ?Throwable $exception) use (
                $attributeTracker,
            ) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());

                $attributes = $attributeTracker->trackRedisAttributes($redis);
                $span->setAttributes($attributes);

                self::end($exception);
            },
        );
        hook(
            \Redis::class,
            'pconnect',
            pre: static function (
                \Redis $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder($instrumentation, 'Redis::pconnect', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if ($class === \Redis::class) {
                    $builder->setAttribute(TraceAttributes::SERVER_ADDRESS, $params[0]);
                    if (!str_starts_with($params[0], 'unix:') && !str_contains($params[0], '/')) {
                        $builder->setAttribute(TraceAttributes::NETWORK_TRANSPORT, 'tcp');
                        $builder->setAttribute(TraceAttributes::SERVER_PORT, $params[1] ?? 6379);
                    } else {
                        $builder->setAttribute(TraceAttributes::NETWORK_TRANSPORT, 'unix');
                    }
                    if (
                        isset($params[6])
                        && array_key_exists('auth', $params[6])
                        && is_array($auth = $params[6]['auth'])
                        && count($auth) > 1
                    ) {
                        $builder->setAttribute(TraceAttributes::DB_USER, $auth[0]);
                    }
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (\Redis $redis, array $params, mixed $ret, ?Throwable $exception) use (
                $attributeTracker,
            ) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());

                $attributes = $attributeTracker->trackRedisAttributes($redis);
                $span->setAttributes($attributes);

                self::end($exception);
            },
        );
        hook(
            \Redis::class,
            'select',
            pre: static function (
                \Redis $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($attributeTracker, $instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder($instrumentation, 'Redis::select', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                $attributes = $attributeTracker->trackedAttributesForRedis($redis);
                $attributes[TraceAttributes::DB_REDIS_DATABASE_INDEX] = $params[0];
                $span->setAttributes($attributes);
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (\Redis $redis, array $params, mixed $ret, ?Throwable $exception) use (
                $attributeTracker,
            ) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());

                $attributes = $attributeTracker->trackRedisAttributes($redis);
                $span->setAttributes($attributes);

                self::end($exception);
            },
        );
        hook(
            \Redis::class,
            'reset',
            pre: static function (
                \Redis $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($attributeTracker, $instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder($instrumentation, 'Redis::reset', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                $attributes = $attributeTracker->trackedAttributesForRedis($redis);
                $span->setAttributes($attributes);
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (\Redis $redis, array $params, mixed $ret, ?Throwable $exception) use (
                $attributeTracker,
            ) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());

                $attributes = $attributeTracker->trackRedisAttributes($redis);
                $span->setAttributes($attributes);

                self::end($exception);
            },
        );
        hook(
            \Redis::class,
            'exists',
            pre: self::generateVarargsPreHook('exists', $instrumentation, $attributeTracker),
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'get',
            pre: static function (
                \Redis $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($attributeTracker, $instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder($instrumentation, 'Redis::get', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if ($class === \Redis::class) {
                    $builder->setAttribute(
                        TraceAttributes::DB_STATEMENT,
                        isset($params[0]) ? 'GET ' . $params[0] : 'undefined',
                    );
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();

                $attributes = $attributeTracker->trackedAttributesForRedis($redis);
                $span->setAttributes($attributes);

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'mget',
            pre: self::generateVarargsPreHook('mGet', $instrumentation, $attributeTracker),
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'set',
            pre: static function (
                \Redis $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($attributeTracker, $instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder($instrumentation, 'Redis::set', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if ($class === \Redis::class) {
                    $statement = 'SET ' . $params[0] . ' ?';
                    if (isset($params[2])) {
                        // Third param could be the expiry time
                        // @see: https://github.com/phpredis/phpredis?tab=readme-ov-file#parameters-23
                        if (is_int($params[2])) {
                            $params[2] = ['EX' => $params[2]];
                        }

                        foreach ($params[2] as $key => $value) {
                            $statement .= ' ' . strtoupper((string) $key) . ' ' . $value;
                        }
                    }
                    $builder->setAttribute(
                        TraceAttributes::DB_STATEMENT,
                        $statement,
                    );
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();

                $attributes = $attributeTracker->trackedAttributesForRedis($redis);
                $span->setAttributes($attributes);

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'setEx',
            pre: static function (
                \Redis $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($attributeTracker, $instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder($instrumentation, 'Redis::setEx', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if ($class === \Redis::class) {
                    $statement = 'SETEX ' . $params[0] . ' ' . $params[1] . ' ?';
                    $builder->setAttribute(
                        TraceAttributes::DB_STATEMENT,
                        $statement,
                    );
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();

                $attributes = $attributeTracker->trackedAttributesForRedis($redis);
                $span->setAttributes($attributes);

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'scan',
            pre: static function (
                \Redis $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($attributeTracker, $instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder($instrumentation, 'Redis::scan', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if ($class === \Redis::class) {
                    $statement = 'SCAN ' . $params[0];
                    if (!empty($params[1])) {
                        $statement .= ' MATCH ' . $params[1];
                    }
                    if (!empty($params[2])) {
                        $statement .= ' COUNT ' . $params[2];
                    }
                    if (!empty($params[3])) {
                        $statement .= ' TYPE ' . $params[3];
                    }
                    $builder->setAttribute(
                        TraceAttributes::DB_STATEMENT,
                        $statement,
                    );
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();

                $attributes = $attributeTracker->trackedAttributesForRedis($redis);
                $span->setAttributes($attributes);

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'delete',
            pre: self::generateVarargsPreHook('delete', $instrumentation, $attributeTracker),
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'del',
            pre: self::generateVarargsPreHook('del', $instrumentation, $attributeTracker),
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'unlink',
            pre: self::generateVarargsPreHook('unlink', $instrumentation, $attributeTracker),
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'sAdd',
            pre: static function (
                \Redis $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($attributeTracker, $instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder($instrumentation, 'Redis::sAdd', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if ($class === \Redis::class) {
                    $statement = 'SADD';
                    $maskValues = false;
                    foreach ($params as $value) {
                        if ($maskValues) {
                            $statement .= ' ?';

                            continue;
                        }
                        $maskValues = true;
                        $statement .= " $value";
                    }
                    $builder->setAttribute(
                        TraceAttributes::DB_STATEMENT,
                        $statement,
                    );
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();

                $attributes = $attributeTracker->trackedAttributesForRedis($redis);
                $span->setAttributes($attributes);

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'sRem',
            pre: static function (
                \Redis $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($attributeTracker, $instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder($instrumentation, 'Redis::sRem', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if ($class === \Redis::class) {
                    $statement = 'SREM';
                    $maskValues = false;
                    foreach ($params as $value) {
                        if ($maskValues) {
                            $statement .= ' ?';

                            continue;
                        }
                        $maskValues = true;
                        $statement .= " $value";
                    }
                    $builder->setAttribute(
                        TraceAttributes::DB_STATEMENT,
                        $statement,
                    );
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();

                $attributes = $attributeTracker->trackedAttributesForRedis($redis);
                $span->setAttributes($attributes);

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'multi',
            pre: self::generateSimplePreHook('multi', $instrumentation, $attributeTracker),
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'pipeline',
            pre: self::generateSimplePreHook('pipeline', $instrumentation, $attributeTracker),
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'exec',
            pre: self::generateSimplePreHook('exec', $instrumentation, $attributeTracker),
            post: $genericPostHook,
        );
        hook(
            \Redis::class,
            'multi',
            pre: self::generateSimplePreHook('discard', $instrumentation, $attributeTracker),
            post: $genericPostHook,
        );
    }

    private static function makeBuilder(
        CachedInstrumentation $instrumentation,
        string $name,
        string $function,
        string $class,
        ?string $filename,
        ?int $lineno,
    ): SpanBuilderInterface {
        /** @psalm-suppress ArgumentTypeCoercion */
        return $instrumentation->tracer()
            ->spanBuilder($name)
            ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
            ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
            ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
            ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);
    }

    private static function generateSimplePreHook(
        string $command,
        CachedInstrumentation $instrumentation,
        RedisAttributeTracker $attributeTracker,
    ): callable {
        return static function (
            \Redis $redis,
            array $params,
            string $class,
            string $function,
            ?string $filename,
            ?int $lineno,
        ) use ($attributeTracker, $instrumentation, $command) {
            /** @psalm-suppress ArgumentTypeCoercion */
            $builder = self::makeBuilder($instrumentation, "Redis::$command", $function, $class, $filename, $lineno)
                ->setSpanKind(SpanKind::KIND_CLIENT);
            $parent = Context::getCurrent();
            $span = $builder->startSpan();
            $attributes = $attributeTracker->trackedAttributesForRedis($redis);
            $span->setAttributes($attributes);
            Context::storage()->attach($span->storeInContext($parent));
        };
    }

    private static function generateVarargsPreHook(
        string $command,
        CachedInstrumentation $instrumentation,
        RedisAttributeTracker $attributeTracker,
    ): callable {
        return static function (
            \Redis $redis,
            array $params,
            string $class,
            string $function,
            ?string $filename,
            ?int $lineno,
        ) use ($attributeTracker, $instrumentation, $command) {
            /** @psalm-suppress ArgumentTypeCoercion */
            $builder = self::makeBuilder($instrumentation, "Redis::$command", $function, $class, $filename, $lineno)
                ->setSpanKind(SpanKind::KIND_CLIENT);
            if ($class === \Redis::class) {
                if (isset($params[0]) && is_array($params[0])) {
                    $params = $params[0];
                }
                $builder->setAttribute(
                    TraceAttributes::DB_STATEMENT,
                    isset($params[0]) ? strtoupper($command) . ' ' . (implode(' ', $params)) : 'undefined',
                );
            }
            $parent = Context::getCurrent();
            $span = $builder->startSpan();

            $attributes = $attributeTracker->trackedAttributesForRedis($redis);
            $span->setAttributes($attributes);

            Context::storage()->attach($span->storeInContext($parent));
        };
    }

    private static function end(?Throwable $exception): void
    {
        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }
        $scope->detach();
        $span = Span::fromContext($scope->context());
        if ($exception) {
            $span->recordException($exception, [TraceAttributes::EXCEPTION_ESCAPED => true]);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        }

        $span->end();
    }
}
