<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('Property Video & Available Rooms API', function () {
    it('stores property with video file and url', function () {
        Storage::fake('public');

        $user = User::factory()->owner()->create();
        $video = UploadedFile::fake()->create('tour.mp4', 5000, 'video/mp4');

        $response = $this->actingAs($user)->post(route('properties.store'), [
            'name' => 'Kos Indah Video Test',
            'video' => $video,
        ]);

        $response->assertRedirect();

        $property = Property::where('name', 'Kos Indah Video Test')->first();
        expect($property)->not->toBeNull();
        expect($property->video)->not->toBeNull();
        expect($property->video_url)->toContain('/storage/properties/videos/');
    });

    it('returns property video_url in available rooms API', function () {
        $property = Property::factory()->create([
            'is_active' => true,
            'name' => 'Property Video API Test',
            'video' => 'https://example.com/property-tour.mp4',
        ]);

        $response = $this->getJson(route('api.v1.available-rooms'));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $propertyData = collect($response->json('data'))->firstWhere('slug', $property->slug);
        expect($propertyData)->not->toBeNull();
        expect($propertyData['video_url'])->toBe('https://example.com/property-tour.mp4');
    });
});
