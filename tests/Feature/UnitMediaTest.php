<?php

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('Unit Media & Available Rooms API', function () {
    it('stores unit with image and video files', function () {
        Storage::fake('public');

        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $image = UploadedFile::fake()->image('room.jpg');
        $video = UploadedFile::fake()->create('room_tour.mp4', 5000, 'video/mp4');

        $response = $this->actingAs($user)->post(route('properties.units.store', $property), [
            'name' => 'Kamar 101',
            'floor' => '1',
            'capacity' => 1,
            'image' => $image,
            'video' => $video,
        ]);

        $response->assertRedirect();

        $unit = Unit::where('name', 'Kamar 101')->first();
        expect($unit)->not->toBeNull();
        expect($unit->image)->not->toBeNull();
        expect($unit->video)->not->toBeNull();
        expect($unit->image_url)->toContain('/storage/units/images/');
        expect($unit->video_url)->toContain('/storage/units/videos/');
    });

    it('returns room image_url and video_url in available rooms API', function () {
        $property = Property::factory()->create([
            'is_active' => true,
        ]);

        $unit = Unit::factory()->create([
            'property_id' => $property->id,
            'name' => 'Kamar Deluxe 201',
            'status' => 'available',
            'image' => 'https://example.com/rooms/deluxe201.jpg',
            'video' => 'https://example.com/tours/deluxe201.mp4',
        ]);

        $response = $this->getJson(route('api.v1.available-rooms'));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $propertyData = collect($response->json('data'))->firstWhere('slug', $property->slug);
        expect($propertyData)->not->toBeNull();

        $roomDetails = collect($propertyData['available_room_details'])->firstWhere('name', 'Kamar Deluxe 201');
        expect($roomDetails)->not->toBeNull();
        expect($roomDetails['image_url'])->toBe('https://example.com/rooms/deluxe201.jpg');
        expect($roomDetails['video_url'])->toBe('https://example.com/tours/deluxe201.mp4');
    });
});
