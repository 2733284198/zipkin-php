<?php

/**
 * Copyright 2020 OpenZipkin Authors
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace ZipkinTests\Unit\Reporters;

use Zipkin\Reporters\JsonV2Serializer;
use Zipkin\Recording\Span;
use Zipkin\Propagation\TraceContext;
use Zipkin\Endpoint;
use PHPUnit\Framework\TestCase;

final class JsonV2SerializerTest extends TestCase
{
    public function testSpanIsSerializedSuccessfully()
    {
        $context = TraceContext::create('186f11b67460db4d', '186f11b67460db4d');
        $localEndpoint = Endpoint::create('service1', '192.168.0.11', null, 3301);
        $span = Span::createFromContext($context, $localEndpoint);
        $startTime = 1594044779509687;
        $span->start($startTime);
        $span->setName('Test');
        $span->setKind('CLIENT');
        $remoteEndpoint = Endpoint::create('SERVICE2', null, '2001:0db8:85a3:0000:0000:8a2e:0370:7334', 3302);
        $span->setRemoteEndpoint($remoteEndpoint);
        $span->tag('test_key', 'test_value');
        $span->annotate($startTime + 100, 'test_annotarion');
        $span->setError(new \RuntimeException('test_error'));
        $span->finish($startTime + 1000);
        $serializer = new JsonV2Serializer();
        $serializedSpans = $serializer->serialize([$span]);

        $expectedSerialization = '[{'
            . '"id":"186f11b67460db4d","traceId":"186f11b67460db4d","timestamp":1594044779509687,"name":"test",'
            . '"duration":1000,"localEndpoint":{"serviceName":"service1","ipv4":"192.168.0.11","port":3301},'
            . '"kind":"CLIENT",'
            . '"remoteEndpoint":{"serviceName":"service2","ipv6":"2001:0db8:85a3:0000:0000:8a2e:0370:7334","port":3302}'
            . ',"annotations":[{"value":"test_annotarion","timestamp":1594044779509787}],'
            . '"tags":{"test_key":"test_value","error":"test_error"}'
            . '}]';
        $this->assertEquals($expectedSerialization, $serializedSpans);
    }

    public function testErrorTagIsNotClobberedBySpanError()
    {
        $context = TraceContext::create('186f11b67460db4d', '186f11b67460db4d');
        $localEndpoint = Endpoint::create('service1', '192.168.0.11', null, 3301);
        $span = Span::createFromContext($context, $localEndpoint);
        $startTime = 1594044779509688;
        $span->start($startTime);
        $span->setName('test');
        $span->tag('test_key', 'test_value');
        $span->tag('error', 'priority_error');
        $span->setError(new \RuntimeException('test_error'));
        $span->finish($startTime + 1000);
        $serializer = new JsonV2Serializer();
        $serializedSpans = $serializer->serialize([$span]);

        $expectedSerialization = '[{'
            . '"id":"186f11b67460db4d","traceId":"186f11b67460db4d","timestamp":1594044779509688,"name":"test",'
            . '"duration":1000,"localEndpoint":{"serviceName":"service1","ipv4":"192.168.0.11","port":3301},'
            . '"tags":{"test_key":"test_value","error":"priority_error"}'
            . '}]';
        $this->assertEquals($expectedSerialization, $serializedSpans);
    }
}