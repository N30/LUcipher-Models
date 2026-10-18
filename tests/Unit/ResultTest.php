<?php

namespace All1\LuModels\Tests\Unit;

use All1\LuModels\Support\Result;
use All1\LuModels\Tests\Fixtures\Widget;
use All1\LuModels\Tests\UnitTestCase;
use Illuminate\Support\MessageBag;

/** Phase 4 §2: one Result out of every write, presented by the transports. */
class ResultTest extends UnitTestCase
{
    public function test_a_successful_write_carries_the_record_message_and_key(): void
    {
        $widget = new Widget(['name' => 'w']);
        $widget->id = 7;

        $result = Result::ok('Saved', $widget, null, 'enc-7');

        $this->assertFalse($result->failed());
        $this->assertTrue($result->found());
        $this->assertSame($widget, $result->model);
        $this->assertSame($widget, $result->data, 'data defaults to the record');
        $this->assertSame('enc-7', $result->redirectKey());
        $this->assertSame(200, $result->status);

        $json = $result->toJsonResponse()->getData(true);
        $this->assertSame(['message' => 'Saved', 'data' => ['name' => 'w', 'id' => 7], 'id' => 'enc-7'], $json);
    }

    public function test_without_an_encrypted_key_the_redirect_uses_the_records_own(): void
    {
        $widget = new Widget;
        $widget->id = 3;

        $this->assertSame(3, Result::ok('Saved', $widget)->redirectKey());
        $this->assertArrayNotHasKey('id', Result::ok('Saved', $widget)->toJsonResponse()->getData(true));
    }

    public function test_refused_input_is_422_with_errors_by_field(): void
    {
        $result = Result::invalid(new MessageBag(['name' => ['The name field cannot be written.']]));

        $this->assertTrue($result->failed());
        $this->assertSame(422, $result->status);
        $response = $result->toJsonResponse();
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(['name' => ['The name field cannot be written.']], $response->getData(true)['errors']);
        $this->assertSame('There was an issue with your input.', $response->getData(true)['message']);
    }

    public function test_a_missing_record_is_404_without_errors(): void
    {
        $result = Result::notFound();

        $this->assertFalse($result->failed());
        $this->assertFalse($result->found());
        $this->assertSame(404, $result->toJsonResponse()->getStatusCode());
        $this->assertNull($result->redirectKey());
        $this->assertSame(['message' => 'Record not found', 'data' => []], $result->toArray());
    }
}
