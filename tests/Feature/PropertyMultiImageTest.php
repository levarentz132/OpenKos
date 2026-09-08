<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('property stores and retrieves multiple gallery images and returns image_urls in available rooms api', function () {
    Storage::fake('public');

    $user = User::factory()->owner()->create();

    $image1 = UploadedFile::fake()->image('photo1.jpg');
    $image2 = UploadedFile::fake()->image('photo2.png');

    $response = $this->actingAs($user)->post(route('properties.store'), [
        'name' => 'Kos Multi Gallery Test',
        'images' => [$image1, $image2],
        'video' => 'https://www.youtube.com/watch?v=samplevideo',
    ]);

    $response->assertRedirect();

    $property = Property::where('name', 'Kos Multi Gallery Test')->firstOrFail();

    expect($property->images)->toBeArray()->toHaveCount(2);
    expect($property->image_urls)->toBeArray()->toHaveCount(2);
    expect($property->video_url)->toBe('https://www.youtube.com/watch?v=samplevideo');

    // Test API response
    $apiResponse = $this->getJson('/api/v1/available-rooms?property_id='.$property->id);

    $apiResponse->assertOk()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonPath('data.0.name', 'Kos Multi Gallery Test')
        ->assertJsonPath('data.0.video_url', 'https://www.youtube.com/watch?v=samplevideo');

    $data = $apiResponse->json('data.0');

    expect($data)->toHaveKey('image_url');
    expect($data)->toHaveKey('image_urls');
    expect($data['image_urls'])->toBeArray()->toHaveCount(2);
    expect($data['image_url'])->toBe($data['image_urls'][0]);
});
