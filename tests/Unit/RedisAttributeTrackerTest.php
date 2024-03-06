<?php

declare(strict_types=1);

namespace OpenTelemetry\Tests\Instrumentation\Redis\Unit;

use OpenTelemetry\Contrib\Instrumentation\Redis\RedisAttributeTracker;
use OpenTelemetry\SemConv\TraceAttributes;
use PHPUnit\Framework\TestCase;
use Predis\Connection\NodeConnectionInterface;

class RedisAttributeTrackerTest extends TestCase
{
    public function testRedisCanBeTracked(): void
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects(self::once())
            ->method('getHost')
            ->willReturn('redis');

        $redis->expects(self::once())
            ->method('getPort')
            ->willReturn(6379);

        $redis->expects(self::once())
            ->method('getDbNum')
            ->willReturn(7);

        $redis->expects(self::once())
            ->method('getAuth')
            ->willReturn(['test', 'passwd']);

        $objectMap = new RedisAttributeTracker();
        $objectMap->trackRedisAttributes($redis);
        $attributes = $objectMap->trackedAttributesForRedis($redis);

        /** @psalm-suppress InvalidArgument */
        $this->assertContains(TraceAttributes::DB_SYSTEM, array_keys($attributes));
        $this->assertContains(TraceAttributes::DB_USER, array_keys($attributes));
        $this->assertContains(TraceAttributes::DB_REDIS_DATABASE_INDEX, array_keys($attributes));
        $this->assertContains(TraceAttributes::SERVER_ADDRESS, array_keys($attributes));
        $this->assertContains(TraceAttributes::SERVER_PORT, array_keys($attributes));
        /** @psalm-suppress InvalidArrayAccess */
        $this->assertEquals('redis', $attributes[TraceAttributes::DB_SYSTEM]);
        $this->assertEquals('test', $attributes[TraceAttributes::DB_USER]);
        $this->assertEquals(7, $attributes[TraceAttributes::DB_REDIS_DATABASE_INDEX]);
        $this->assertEquals('redis', $attributes[TraceAttributes::SERVER_ADDRESS]);
        $this->assertEquals(6379, $attributes[TraceAttributes::SERVER_PORT]);
    }

    public function testPredisCanBeTracked(): void
    {
        $redis = $this->createMock(\Predis\Client::class);
        $conn = $this->getMockForAbstractClass(NodeConnectionInterface::class);

        $redis->expects(self::once())
            ->method('getConnection')
            ->willReturn($conn);

        $conn->expects(self::once())
            ->method('getParameters')
            ->willReturn(
                (object) [
                    'scheme' => 'tcp',
                    'host' => 'redis',
                    'port' => 6379,
                    'username' => 'test',
                    'password' => 'passwd',
                    'parameters' => (object) [
                        'database' => 7,
                    ],
                ],
            );

        $objectMap = new RedisAttributeTracker();
        $objectMap->trackRedisAttributes($redis);
        $attributes = $objectMap->trackedAttributesForRedis($redis);

        /** @psalm-suppress InvalidArgument */
        $this->assertContains(TraceAttributes::DB_SYSTEM, array_keys($attributes));
        $this->assertContains(TraceAttributes::DB_USER, array_keys($attributes));
        $this->assertContains(TraceAttributes::DB_REDIS_DATABASE_INDEX, array_keys($attributes));
        $this->assertContains(TraceAttributes::SERVER_ADDRESS, array_keys($attributes));
        $this->assertContains(TraceAttributes::SERVER_PORT, array_keys($attributes));
        /** @psalm-suppress InvalidArrayAccess */
        $this->assertEquals('redis', $attributes[TraceAttributes::DB_SYSTEM]);
        $this->assertEquals('test', $attributes[TraceAttributes::DB_USER]);
        $this->assertEquals(7, $attributes[TraceAttributes::DB_REDIS_DATABASE_INDEX]);
        $this->assertEquals('redis', $attributes[TraceAttributes::SERVER_ADDRESS]);
        $this->assertEquals(6379, $attributes[TraceAttributes::SERVER_PORT]);
    }
}
