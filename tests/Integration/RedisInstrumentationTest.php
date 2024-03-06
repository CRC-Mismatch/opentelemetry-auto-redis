<?php

declare(strict_types=1);

namespace Integration;

use ArrayObject;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\TraceAttributes;
use PHPUnit\Framework\TestCase;

class RedisInstrumentationTest extends TestCase
{
    private ScopeInterface $scope;
    private ArrayObject $storage;
    private TracerProvider $tracerProvider;

    private function createClient(): \Redis
    {
        $redis = new \Redis(
            [
                'host' => 'redis',
                'port' => 6379,
                'auth' => ['test', 'passwd'],
            ],
        );
        $redis->select(7);

        return $redis;
    }

    public function setUp(): void
    {
        $this->storage = new ArrayObject();
        $this->tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(
                new InMemoryExporter($this->storage),
            ),
        );

        $this->scope = Configurator::create()
            ->withTracerProvider($this->tracerProvider)
            ->activate();
    }

    public function tearDown(): void
    {
        $this->scope->detach();
    }

    public function test_redis_construct(): void
    {
        // @var ImmutableSpan $span
        $this->assertCount(0, $this->storage);
        self::createClient();
        $this->assertCount(2, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(0);
        $this->assertSame('Redis::__construct', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertFalse($span->getAttributes()->has(TraceAttributes::DB_REDIS_DATABASE_INDEX));
        $span = $this->storage->offsetGet(1);
        $this->assertSame('Redis::select', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertEquals(7, $span->getAttributes()->get(TraceAttributes::DB_REDIS_DATABASE_INDEX));
    }

    public function test_redis_connect(): void
    {
        // @var ImmutableSpan $span
        $this->assertCount(0, $this->storage);

        $redis = new \Redis();
        $this->assertCount(1, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(0);
        $this->assertSame('Redis::__construct', $span->getName());
        $this->assertEquals(3, $span->getAttributes()->count());

        $result = $redis->connect('redis', context: ['auth' => ['test', 'passwd']]);
        $this->assertTrue($result);
        $this->assertCount(2, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(1);
        $this->assertSame('Redis::connect', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertFalse($span->getAttributes()->has(TraceAttributes::DB_REDIS_DATABASE_INDEX));

        $redis->select(7);
        $this->assertCount(3, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(2);
        $this->assertSame('Redis::select', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertEquals(7, $span->getAttributes()->get(TraceAttributes::DB_REDIS_DATABASE_INDEX));
    }

    public function test_instrumented_methods(): void
    {
        $redis = self::createClient();

        $redis->set('test-set', 'OK');
        $this->assertCount(3, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(2);
        $this->assertSame('Redis::set', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('SET test-set ?', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));

        $redis->setex('test-setex', 60, 'OK');
        $this->assertCount(4, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(3);
        $this->assertSame('Redis::setEx', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('SETEX test-setex 60 ?', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));

        $redis->sadd('test-sadd', 'test', 'result', 'OK');
        $this->assertCount(5, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(4);
        $this->assertSame('Redis::sAdd', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('SADD test-sadd ? ? ?', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));

        $value = $redis->get('test-set');
        $this->assertCount(6, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(5);
        $this->assertSame('Redis::get', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('GET test-set', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
        $this->assertSame('OK', $value);

        $value = $redis->mget(['test-setex']);
        $this->assertCount(7, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(6);
        $this->assertSame('Redis::mGet', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('MGET test-setex', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
        $this->assertSame('OK', $value[0]);

        $redis->srem('test-sadd', 'result');
        $this->assertCount(8, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(7);
        $this->assertSame('Redis::sRem', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('SREM test-sadd ?', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
    }
}
