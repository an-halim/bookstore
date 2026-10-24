<?php

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('keeps catalog reads public and requires auth for writes', function () {
    $category = Category::factory()->create();

    $this->getJson('/api/categories')->assertOk();
    $this->postJson('/api/categories', ['name' => 'History'])->assertUnauthorized();
    $this->getJson("/api/categories/{$category->id}")->assertOk();

    $this->assertDatabaseCount('categories', 1);
});

it('returns 403 when a regular user manages the catalog', function () {
    $category = Category::factory()->create();
    $book = Book::factory()->for($category)->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/categories', ['name' => 'History'])->assertForbidden();
    $this->patchJson("/api/categories/{$category->id}", ['name' => 'History'])->assertForbidden();
    $this->deleteJson("/api/categories/{$category->id}")->assertForbidden();
    $this->postJson('/api/books', [])->assertForbidden();
    $this->patchJson("/api/books/{$book->id}", ['title' => 'Changed'])->assertForbidden();
    $this->deleteJson("/api/books/{$book->id}")->assertForbidden();

    $this->assertModelExists($category);
    $this->assertModelExists($book);
});

it('lets an admin manage categories and books', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $category = $this->postJson('/api/categories', ['name' => 'History'])
        ->assertCreated()
        ->json('data.id');

    $this->postJson('/api/books', [
        'category_id' => $category,
        'title' => 'A Book',
        'author' => 'An Author',
        'isbn' => '9780132350884',
        'total_copies' => 2,
    ])->assertCreated()->assertJsonPath('data.available_copies', 2);

    $this->assertDatabaseHas('books', ['isbn' => '9780132350884']);
});

it('applies role changes to an existing bearer token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/categories', ['name' => 'Before'])->assertForbidden();

    $this->artisan('users:set-role', ['email' => $user->email, 'role' => 'admin'])->assertSuccessful();
    Auth::forgetGuards();
    $this->withToken($token)->postJson('/api/categories', ['name' => 'Allowed'])->assertCreated();

    $this->artisan('users:set-role', ['email' => $user->email, 'role' => 'user'])->assertSuccessful();
    Auth::forgetGuards();
    $this->withToken($token)->postJson('/api/categories', ['name' => 'After'])->assertForbidden();

    $this->assertDatabaseCount('categories', 1);
});

it('returns 401 when a guest attempts to borrow', function () {
    $book = Book::factory()->create();

    $this->postJson('/api/loans', ['book_id' => $book->id])->assertUnauthorized();
    $this->getJson('/api/loans')->assertUnauthorized();

    $this->assertDatabaseCount('loans', 0);
});
