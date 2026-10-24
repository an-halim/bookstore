<?php

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('creates and lists books with their categories', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $category = Category::factory()->create();

    $this->postJson('/api/books', [
        'category_id' => $category->id,
        'title' => 'Clean Code',
        'author' => 'Robert Martin',
        'isbn' => '9780132350884',
        'total_copies' => 2,
        'available_copies' => 99,
    ])->assertCreated()->assertJsonPath('data.available_copies', 2);

    $this->getJson('/api/books')->assertOk()->assertJsonPath('data.0.category.id', $category->id);

    $this->assertDatabaseHas('books', ['isbn' => '9780132350884', 'available_copies' => 2]);
});

it('rejects an unknown category and duplicate ISBN', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $book = Book::factory()->create();

    $this->postJson('/api/books', [
        'category_id' => 999,
        'title' => 'Other',
        'author' => 'Author',
        'isbn' => $book->isbn,
        'total_copies' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors(['category_id', 'isbn']);

    $this->assertDatabaseCount('books', 1);
});

it('updates and deletes a book', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $book = Book::factory()->create();

    $this->patchJson("/api/books/{$book->id}", ['title' => 'New Title', 'total_copies' => 5])
        ->assertOk()
        ->assertJsonPath('data.available_copies', 5);

    $this->deleteJson("/api/books/{$book->id}")->assertNoContent();

    $this->assertSoftDeleted($book);
    $this->getJson("/api/books/{$book->id}")->assertNotFound();
});

it('keeps a category that still contains books', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $book = Book::factory()->create();

    $this->deleteJson("/api/categories/{$book->category_id}")->assertStatus(409);

    $this->assertModelExists($book->category);
});

it('keeps a category referenced by an archived book', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $book = Book::factory()->create();

    $this->deleteJson("/api/books/{$book->id}")->assertNoContent();
    $this->deleteJson("/api/categories/{$book->category_id}")->assertStatus(409);

    $this->assertModelExists($book->category);
});

it('reserves the ISBN of an archived book', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $book = Book::factory()->create();
    $this->deleteJson("/api/books/{$book->id}")->assertNoContent();

    $this->postJson('/api/books', [
        'category_id' => $book->category_id,
        'title' => 'Another Copy',
        'author' => 'Another Author',
        'isbn' => $book->isbn,
        'total_copies' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('isbn');

    $this->assertDatabaseCount('books', 1);
});
