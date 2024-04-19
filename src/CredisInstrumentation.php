<?php

/**
 * @copyright  Copyright (c) 2024 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\Redis;

use Credis_Client;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use function OpenTelemetry\Instrumentation\hook;
use OpenTelemetry\SemConv\TraceAttributes;

use Throwable;

class CredisInstrumentation
{
    public const NAME = 'credis';

    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation('io.opentelemetry.contrib.php.credis');
        $attributeTracker = new RedisAttributeTracker();
        hook(
            Credis_Client::class,
            '__construct',
            pre: static function (
                Credis_Client $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($instrumentation) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder(
                    $instrumentation,
                    'Credis_Client::__construct',
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if ($class === Credis_Client::class) {
                    $schemeMatches = [];
                    preg_match('#^(tcp|unix)://(.+)$#', $params[0], $schemeMatches);
                    $builder->setAttribute(TraceAttributes::SERVER_ADDRESS, $params[0] ?? '127.0.0.1')
                        ->setAttribute(TraceAttributes::SERVER_PORT, $params[1] ?? 6379)
                        ->setAttribute(TraceAttributes::NETWORK_TRANSPORT, $schemeMatches[1] ?? 'tcp')
                        ->setAttribute(TraceAttributes::DB_REDIS_DATABASE_INDEX, $params[4] ?? 0);
                    if (!empty($params[6] ?? '')) {
                        $builder->setAttribute(TraceAttributes::DB_USER, $params[6]);
                    }
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (Credis_Client $redis, array $params, mixed $statement, ?Throwable $exception) use (
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
            Credis_Client::class,
            'auth',
            pre: static function (
                Credis_Client $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($attributeTracker) {
                if (!empty($params[1] ?? null)) {
                    $attributeTracker->trackRedisAuth($redis, $params[1]);
                }
            },
        );
        hook(
            Credis_Client::class,
            '__call',
            pre: static function (
                Credis_Client $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($attributeTracker, $instrumentation) {
                $name = strtolower($params[0]);
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = self::makeBuilder(
                    $instrumentation,
                    "Credis_Client::$name",
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                $parent = Context::getCurrent();
                $span = $builder->startSpan();

                $attributes = $attributeTracker->trackedAttributesForRedis($redis);
                $span->setAttributes($attributes);

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (
                Credis_Client $redis,
                array $params,
                mixed $statement,
                ?Throwable $exception
            ) use ($attributeTracker) {
                if ($params[0] === 'select' && is_int($dbIndex = $params[1][0])) {
                    $attributeTracker->trackRedisDbIdx($redis, $dbIndex);
                }
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                self::end($exception);
            },
        );
        hook(
            Credis_Client::class,
            '_prepare_command',
            pre: static function (
                Credis_Client|string $redis,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());
                $statement = '';
                $mask = false;
                foreach ($params[0] as $arg) {
                    $statement .= $mask ? ' ?' : $arg;
                    $mask = true;
                }
                $span->setAttribute(TraceAttributes::DB_STATEMENT, $statement);
            },
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
