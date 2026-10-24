<?php

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates and lists categories', function () {
    $this->postJson('/api/categories', ['name' => 'Fiction', 'description' => 'Novels'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Fiction');

    $this->getJson('/api/categories')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Fiction');

    $this->assertDatabaseHas('categories', ['name' => 'Fiction']);
});

it('rejects duplicate category names', function () {
    Category::factory()->create(['name' => 'Fiction']);

    $this->postJson('/api/categories', ['name' => 'Fiction'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->assertDatabaseCount('categories', 1);
});

it('updates and deletes a category', function () {
    $category = Category::factory()->create();

    $this->patchJson("/api/categories/{$category->id}", ['name' => 'History'])
        ->assertOk()
        ->assertJsonPath('data.name', 'History');

    $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});
