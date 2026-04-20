<?php

namespace Tests\Feature;

use App\Events\UserTransferred;
use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_transferred_event_is_dispatched_via_observer_on_transfer(): void
    {
        Event::fake([UserTransferred::class]);

        $company = Company::factory()->create();
        $storeA = Store::factory()->create(['company_id' => $company->id]);
        $storeB = Store::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $storeA->id,
        ]);

        // Simulating a transfer
        $user->update(['store_id' => $storeB->id]);

        Event::assertDispatched(UserTransferred::class, function ($event) use ($user, $storeA, $storeB) {
            return $event->user->id === $user->id &&
                   $event->oldStore->id === $storeA->id &&
                   $event->newStore->id === $storeB->id;
        });
    }

    public function test_user_transferred_event_is_NOT_dispatched_on_first_assignment(): void
    {
        Event::fake([UserTransferred::class]);

        $company = Company::factory()->create();
        $store = Store::factory()->create(['company_id' => $company->id]);
        
        // Use factory without store_id initially, but often factories set it. 
        // Let's create a user with store_id = null.
        $user = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => null,
        ]);

        // First assignment
        $user->update(['store_id' => $store->id]);

        Event::assertNotDispatched(UserTransferred::class);
    }

    public function test_handle_user_transfer_listener_invalidates_sessions(): void
    {
        config(['session.driver' => 'database']);
        
        $company = Company::factory()->create();
        $storeA = Store::factory()->create(['company_id' => $company->id]);
        $storeB = Store::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $storeA->id,
        ]);

        DB::table('sessions')->insert([
            'id' => 'session_id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $this->assertDatabaseHas('sessions', ['user_id' => $user->id]);

        // Trigger transfer via model update (caught by observer)
        $user->update(['store_id' => $storeB->id]);

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_handle_user_transfer_listener_sends_notification(): void
    {
        $company = Company::factory()->create();
        $storeA = Store::factory()->create(['company_id' => $company->id]);
        $storeB = Store::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $storeA->id,
        ]);

        // Trigger transfer
        $user->update(['store_id' => $storeB->id]);

        // Check notifications on the freshly loaded record
        $user = $user->fresh();
        $this->assertCount(1, $user->notifications);
        $this->assertEquals(__('app.store_assignment_changed'), $user->notifications->first()->data['title']);
    }
}
