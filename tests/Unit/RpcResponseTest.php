<?php

namespace Tests\Unit;

use App\Support\RpcResponse;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RpcResponseTest extends TestCase
{
    public function test_success_envelope_structure(): void
    {
        $payload = ['id' => 10, 'name' => 'Fast Delivery'];
        $result = RpcResponse::success($payload, 'Created successfully');

        $this->assertTrue($result['success']);
        $this->assertSame($payload, $result['data']);
        $this->assertSame('Created successfully', $result['message']);
        $this->assertSame([], $result['errors']);
    }

    public function test_error_envelope_structure_with_array_errors(): void
    {
        $errors = [
            'name' => ['Name is required', 'Name must be 3 characters'],
            'cost' => ['Cost must be positive'],
        ];

        $result = RpcResponse::error('Validation failed', $errors);

        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertSame('Validation failed', $result['message']);
        $this->assertSame($errors, $result['errors']);
    }

    public function test_error_envelope_normalizes_single_string_errors(): void
    {
        $errors = [
            'name' => 'Name is required',
            'cost' => 'Cost must be numeric',
        ];

        $result = RpcResponse::error('Failed', $errors);

        $this->assertFalse($result['success']);
        $this->assertSame([
            'name' => ['Name is required'],
            'cost' => ['Cost must be numeric'],
        ], $result['errors']);
    }

    public function test_error_envelope_defaults_message_to_first_error(): void
    {
        $errors = [
            'store_id' => ['Please select a store first'],
            'name' => ['Name is required'],
        ];

        $result = RpcResponse::error(null, $errors);

        $this->assertFalse($result['success']);
        $this->assertSame('Please select a store first', $result['message']);
    }

    public function test_from_validator_converts_validation_errors(): void
    {
        $validator = Validator::make(
            ['name' => '', 'cost' => -5],
            [
                'name' => ['required'],
                'cost' => ['numeric', 'min:0'],
            ],
            [
                'name.required' => 'Custom name required',
                'cost.min' => 'Custom cost min',
            ]
        );

        $this->assertTrue($validator->fails());

        $result = RpcResponse::fromValidator($validator);

        $this->assertFalse($result['success']);
        $this->assertSame('Custom name required', $result['message']);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('cost', $result['errors']);
        $this->assertSame(['Custom name required'], $result['errors']['name']);
        $this->assertSame(['Custom cost min'], $result['errors']['cost']);
    }

    public function test_success_envelope_normalizes_arrayable_object(): void
    {
        $arrayable = new class implements Arrayable
        {
            public function toArray(): array
            {
                return ['id' => 101, 'title' => 'VIP Delivery'];
            }
        };

        $result = RpcResponse::success($arrayable, 'Success');

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['data']);
        $this->assertSame(['id' => 101, 'title' => 'VIP Delivery'], $result['data']);
    }

    public function test_success_envelope_normalizes_json_serializable_object(): void
    {
        $serializable = new class implements \JsonSerializable
        {
            public function jsonSerialize(): mixed
            {
                return ['code' => 'DISC-50', 'discount' => 50];
            }
        };

        $result = RpcResponse::success($serializable);

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['data']);
        $this->assertSame(['code' => 'DISC-50', 'discount' => 50], $result['data']);
    }

    public function test_success_envelope_normalizes_stdclass_object(): void
    {
        $obj = new \stdClass;
        $obj->key = 'val';
        $obj->nested = new \stdClass;
        $obj->nested->num = 42;

        $result = RpcResponse::success($obj);

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['data']);
        $this->assertSame([
            'key' => 'val',
            'nested' => ['num' => 42],
        ], $result['data']);
    }

    public function test_success_envelope_normalizes_collection_and_nested_arrayable(): void
    {
        $item1 = new class implements Arrayable
        {
            public function toArray(): array
            {
                return ['id' => 1, 'name' => 'Item 1'];
            }
        };

        $item2 = new class implements Arrayable
        {
            public function toArray(): array
            {
                return ['id' => 2, 'name' => 'Item 2'];
            }
        };

        $collection = collect([$item1, $item2]);

        $result = RpcResponse::success($collection);

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['data']);
        $this->assertSame([
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ], $result['data']);
    }

    public function test_success_envelope_preserves_scalars_and_null(): void
    {
        $this->assertNull(RpcResponse::success(null)['data']);
        $this->assertSame(42, RpcResponse::success(42)['data']);
        $this->assertSame('INV-001', RpcResponse::success('INV-001')['data']);
        $this->assertSame(99.95, RpcResponse::success(99.95)['data']);
        $this->assertTrue(RpcResponse::success(true)['data']);
        $this->assertFalse(RpcResponse::success(false)['data']);
    }
}
