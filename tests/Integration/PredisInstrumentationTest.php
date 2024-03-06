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

class PredisInstrumentationTest extends TestCase
{
    private ScopeInterface $scope;
    private ArrayObject $storage;
    private TracerProvider $tracerProvider;

    private function createClient(): \Predis\Client
    {
        return new \Predis\Client(
            [
                'scheme' => 'tcp',
                'host' => 'redis',
                'port' => 6379,
                'username' => 'test',
                'password' => 'passwd',
                'parameters' => [
                    'database' => '7',
                ],
            ],
        );
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

    public function test_predis_construct(): void
    {
        // @var ImmutableSpan $span
        $this->assertCount(0, $this->storage);
        self::createClient();
        $this->assertCount(1, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(0);
        $this->assertSame('Predis::__construct', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertEquals(7, $span->getAttributes()->get(TraceAttributes::DB_REDIS_DATABASE_INDEX));
    }

    public function test_constructor_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid type for client options');
        new \Predis\Client(['scheme' => 'invalid'], 'invalid');
    }

    public function test_instrumented_methods(): void
    {
        $redis = self::createClient();

        $redis->set('test-set', 'OK');
        $this->assertCount(2, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(1);
        $this->assertSame('Predis::SET', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('SET test-set ?', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));

        $redis->setex('test-setex', 60, 'OK');
        $this->assertCount(3, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(2);
        $this->assertSame('Predis::SETEX', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('SETEX test-setex ? ?', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));

        $redis->sadd('test-sadd', ['test', 'result', 'OK']);
        $this->assertCount(4, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(3);
        $this->assertSame('Predis::SADD', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('SADD test-sadd ? ? ?', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));

        $value = $redis->get('test-set');
        $this->assertCount(5, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(4);
        $this->assertSame('Predis::GET', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('GET test-set', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
        $this->assertSame('OK', $value);

        $value = $redis->mget('test-setex');
        $this->assertCount(6, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(5);
        $this->assertSame('Predis::MGET', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('MGET test-setex', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
        $this->assertSame('OK', $value[0]);

        $redis->srem('test-sadd', 'result');
        $this->assertCount(7, $this->storage);
        /** @var ImmutableSpan $span */
        $span = $this->storage->offsetGet(6);
        $this->assertSame('Predis::SREM', $span->getName());
        $this->assertEquals('redis', $span->getAttributes()->get(TraceAttributes::DB_SYSTEM));
        $this->assertSame('SREM test-sadd ?', $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
    }
}
