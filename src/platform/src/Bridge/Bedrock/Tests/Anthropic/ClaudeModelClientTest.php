<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Anthropic;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use AsyncAws\BedrockRuntime\Input\InvokeModelRequest;
use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use AsyncAws\Core\Configuration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\Anthropic\ClaudeModelClient;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;

final class ClaudeModelClientTest extends TestCase
{
    private const VERSION = '2023-05-31';

    private MockObject&BedrockRuntimeClient $bedrockClient;
    private ClaudeModelClient $modelClient;
    private Claude $model;

    protected function setUp(): void
    {
        $this->model = new Claude('claude-sonnet-4-6');
        $this->bedrockClient = $this->getMockBuilder(BedrockRuntimeClient::class)
            ->setConstructorArgs([
                Configuration::create([Configuration::OPTION_REGION => Configuration::DEFAULT_REGION]),
            ])
            ->onlyMethods(['invokeModel'])
            ->getMock();
    }

    public function testPassesModelIdWithoutSuffix()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('us.anthropic.claude-sonnet-4-6', $arg->getModelId());
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $response = $this->modelClient->request($this->model, ['message' => 'test']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testPassesModelIdWithVersionSuffix()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('us.anthropic.claude-sonnet-4-5-20250929-v1:0', $arg->getModelId());

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $response = $this->modelClient->request(new Claude('claude-sonnet-4-5-20250929'), ['message' => 'test']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testPassesModelIdWithV1Suffix()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('us.anthropic.claude-opus-4-6-v1', $arg->getModelId());

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $response = $this->modelClient->request(new Claude('claude-opus-4-6'), ['message' => 'test']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testPassesModelIdWithCustomOverride()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('us.anthropic.claude-custom-model-v2:0', $arg->getModelId());

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient(
            $this->bedrockClient,
            self::VERSION,
            ['claude-custom-model' => 'claude-custom-model-v2:0']
        );

        $response = $this->modelClient->request(new Claude('claude-custom-model'), ['message' => 'test']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testPassesUnknownModelNameAsIs()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('us.anthropic.claude-unknown-model', $arg->getModelId());

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $response = $this->modelClient->request(new Claude('claude-unknown-model'), ['message' => 'test']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testUnsetsModelName()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                $body = json_decode($arg->getBody(), true);
                $this->assertArrayNotHasKey('model', $body);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $response = $this->modelClient->request($this->model, ['message' => 'test', 'model' => 'claude']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testSetsAnthropicVersion()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                $body = json_decode($arg->getBody(), true);
                $this->assertSame('bedrock-'.self::VERSION, $body['anthropic_version']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $response = $this->modelClient->request($this->model, ['message' => 'test']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testSetsToolOptionsIfToolsEnabled()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                $body = json_decode($arg->getBody(), true);
                $this->assertSame(['type' => 'auto'], $body['tool_choice']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $options = [
            'tools' => ['Tool'],
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testToolChoiceFromCallerIsPreserved()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $body = json_decode($arg->getBody(), true);
                $this->assertSame(['type' => 'tool', 'name' => 'noop'], $body['tool_choice']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $options = [
            'tools' => ['Tool'],
            'tool_choice' => ['type' => 'tool', 'name' => 'noop'],
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testTransformsResponseFormatToOutputConfig()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                $body = json_decode($arg->getBody(), true);
                $this->assertArrayHasKey('output_config', $body);
                $this->assertArrayHasKey('format', $body['output_config']);
                $this->assertSame('json_schema', $body['output_config']['format']['type']);
                $this->assertSame(['type' => 'object', 'properties' => ['foo' => ['type' => 'string']]], $body['output_config']['format']['schema']);
                $this->assertArrayNotHasKey('response_format', $body);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $options = [
            'response_format' => [
                'json_schema' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'foo' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testForwardsRequestMetadata()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame(
                    ['conversation_id' => 'conv-1', 'user' => 'alice'],
                    json_decode($arg->getRequestMetadata(), true),
                );
                $this->assertArrayNotHasKey('request_metadata', json_decode($arg->getBody(), true));

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $options = ['request_metadata' => ['conversation_id' => 'conv-1', 'user' => 'alice']];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testOmitsRequestMetadataWhenNotProvided()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertNull($arg->getRequestMetadata());

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $response = $this->modelClient->request($this->model, ['message' => 'test']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    /**
     * Bedrock rejects the whole invocation when a value falls outside its allowed character
     * set, so the label is scrubbed rather than the call lost.
     */
    public function testScrubsCharactersBedrockRejects()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertSame(
                    ['user' => 'alice_example.com', 'note' => 'caf_'],
                    json_decode($arg->getRequestMetadata(), true),
                );

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $options = ['request_metadata' => ['user' => 'alice<>example.com', 'note' => "caf\u{e9}"]];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testTruncatesRequestMetadataToBedrockLimits()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $metadata = json_decode($arg->getRequestMetadata(), true);
                $this->assertCount(16, $metadata);
                $this->assertSame(256, \strlen($metadata['key0']));

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $metadata = ['key0' => str_repeat('a', 300)];
        for ($i = 1; $i < 20; ++$i) {
            $metadata['key'.$i] = 'value';
        }

        $response = $this->modelClient->request($this->model, ['message' => 'test'], ['request_metadata' => $metadata]);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testSkipsEmptyAndNonScalarRequestMetadata()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertSame(['user' => 'alice'], json_decode($arg->getRequestMetadata(), true));

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $options = ['request_metadata' => [
            'user' => 'alice',
            'missing' => null,
            'blank' => '',
            'nested' => ['a' => 'b'],
        ]];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }
}
