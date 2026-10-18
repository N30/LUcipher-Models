<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Scopes\UserOwnershipScope;
use All1\LuModels\Tests\TestCase;
use App\Models\Memo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Phase 4 §6: one trait wires the ownership observer and scope the package shipped but never used. */
class OwnedByUserTest extends TestCase
{
    protected User $alice;

    protected User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::create(['name' => 'Alice', 'email' => 'alice@example.test']);
        $this->bob = User::create(['name' => 'Bob', 'email' => 'bob@example.test']);
        DB::table('memos')->insert([
            ['user_id' => $this->alice->id, 'body' => 'alice 1'],
            ['user_id' => $this->alice->id, 'body' => 'alice 2'],
            ['user_id' => $this->bob->id, 'body' => 'bob 1'],
        ]);
    }

    public function test_creating_stamps_the_signed_in_user_whatever_the_input_says(): void
    {
        $this->actingAs($this->alice);

        $memo = Memo::create(['body' => 'mine']);
        $this->assertSame($this->alice->id, $memo->user_id);

        $memo = new Memo(['body' => 'planted']);
        $memo->user_id = $this->bob->id;
        $memo->save();
        $this->assertSame($this->alice->id, $memo->fresh()->user_id, 'the owner is the caller, not the payload');
    }

    public function test_each_user_sees_only_their_own_rows(): void
    {
        $this->actingAs($this->alice);
        $this->assertSame(['alice 1', 'alice 2'], Memo::pluck('body')->sort()->values()->all());

        auth()->logout();
        $this->actingAs($this->bob);
        $this->assertSame(['bob 1'], Memo::pluck('body')->all());
        $this->assertNull(Memo::where('body', 'alice 1')->first(), 'another user\'s row is invisible, not forbidden');
    }

    public function test_a_guest_sees_nothing(): void
    {
        $this->assertSame(0, Memo::count());
        $this->assertNull(Memo::first());
        $this->assertStringContainsString('1 = 0', Memo::toSql());
    }

    public function test_the_scope_can_be_lifted_explicitly(): void
    {
        $this->assertSame(3, Memo::withoutGlobalScope(UserOwnershipScope::class)->count());
    }
}
