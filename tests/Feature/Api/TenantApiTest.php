<?php

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('unauthenticated request to tenant api returns 401 unauthorized', function () {
    $response = $this->getJson('/api/v1/tenant/dashboard');
    $response->assertUnauthorized();
});

test('authenticated tenant can retrieve dashboard overview', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $user->id]);
    $property = Property::factory()->create(['name' => 'Kos Kebon Jeruk']);
    $unit = Unit::factory()->create(['property_id' => $property->id, 'name' => 'Kamar 101']);

    $lease = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'status' => LeaseStatus::Active,
        'rent_amount' => 1500000,
    ]);

    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'status' => InvoiceStatus::Pending,
        'total' => 1500000,
        'amount_paid' => 0,
        'due_date' => now()->addDays(5),
    ]);

    $token = $user->createToken('test-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/tenant/dashboard');

    $response->assertOk()
        ->assertJsonPath('tenant.id', $tenant->id)
        ->assertJsonPath('active_lease.id', $lease->id)
        ->assertJsonPath('active_lease.unit.name', 'Kamar 101')
        ->assertJsonPath('active_lease.property.name', 'Kos Kebon Jeruk')
        ->assertJsonPath('account_summary.total_unpaid_invoices', 1)
        ->assertJsonPath('account_summary.total_outstanding_amount', 1500000);
});

test('tenant can list and view their own leases', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $user->id]);

    $lease1 = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'status' => LeaseStatus::Active,
    ]);

    $token = $user->createToken('test-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/tenant/leases');

    $response->assertOk()
        ->assertJsonStructure([
            'current_leases',
            'lease_history',
        ])
        ->assertJsonPath('current_leases.0.id', $lease1->id);

    // Show specific lease
    $showResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/tenant/leases/{$lease1->id}");

    $showResponse->assertOk()
        ->assertJsonPath('lease.id', $lease1->id);
});

test('tenant cannot view another tenants lease', function () {
    $user1 = User::factory()->create();
    $tenant1 = Tenant::factory()->create(['user_id' => $user1->id]);

    $user2 = User::factory()->create();
    $tenant2 = Tenant::factory()->create(['user_id' => $user2->id]);

    $leaseOther = Lease::factory()->create([
        'primary_tenant_id' => $tenant2->id,
        'status' => LeaseStatus::Active,
    ]);

    $token1 = $user1->createToken('test-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token1}")
        ->getJson("/api/v1/tenant/leases/{$leaseOther->id}");

    $response->assertNotFound();
});

test('tenant can list invoices and view invoice details', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $user->id]);
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);

    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'status' => InvoiceStatus::Pending,
        'total' => 2000000,
        'amount_paid' => 500000,
    ]);

    $token = $user->createToken('test-app')->plainTextToken;

    // List invoices
    $listResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/tenant/invoices?status=unpaid');

    $listResponse->assertOk()
        ->assertJsonPath('invoices.data.0.id', $invoice->id)
        ->assertJsonPath('invoices.data.0.outstanding', 1500000);

    // Show single invoice
    $showResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/tenant/invoices/{$invoice->id}");

    $showResponse->assertOk()
        ->assertJsonPath('invoice.id', $invoice->id)
        ->assertJsonPath('invoice.total', 2000000)
        ->assertJsonPath('invoice.amount_paid', 500000);
});

test('tenant can submit manual payment proof for their invoice', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $user->id]);
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);

    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'status' => InvoiceStatus::Pending,
        'total' => 1000000,
    ]);

    $proofFile = UploadedFile::fake()->image('transfer_receipt.jpg');
    $token = $user->createToken('test-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/tenant/invoices/{$invoice->id}/pay", [
            'amount' => 1000000,
            'payment_method' => 'bank_transfer',
            'notes' => 'BCA transfer from John Doe',
            'proof_image' => $proofFile,
        ]);

    $response->assertCreated()
        ->assertJsonPath('payment.amount', 1000000)
        ->assertJsonPath('payment.status', 'pending');

    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'amount' => 1000000,
        'payment_method' => 'bank_transfer',
        'status' => 'pending',
    ]);
});

test('tenant can create and list maintenance tickets', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $user->id]);
    $property = Property::factory()->create();
    $unit = Unit::factory()->create(['property_id' => $property->id]);
    $lease = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'status' => LeaseStatus::Active,
    ]);

    $token = $user->createToken('test-app')->plainTextToken;

    // Create ticket
    $createResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/tenant/maintenance-tickets', [
            'title' => 'AC Not Cooling',
            'description' => 'The air conditioner blows warm air only.',
            'priority' => 'high',
            'location' => 'Main bedroom',
        ]);

    $createResponse->assertCreated()
        ->assertJsonPath('ticket.title', 'AC Not Cooling')
        ->assertJsonPath('ticket.priority', 'high');

    $ticketId = $createResponse->json('ticket.id');

    // List tickets
    $listResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/tenant/maintenance-tickets');

    $listResponse->assertOk()
        ->assertJsonPath('tickets.data.0.id', $ticketId);

    // Show ticket
    $showResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/tenant/maintenance-tickets/{$ticketId}");

    $showResponse->assertOk()
        ->assertJsonPath('ticket.id', $ticketId)
        ->assertJsonPath('ticket.description', 'The air conditioner blows warm air only.');
});
