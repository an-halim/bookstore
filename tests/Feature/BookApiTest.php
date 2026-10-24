<?php

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates and lists books with their categories', function () {
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
    $book = Book::factory()->create();

    $this->patchJson("/api/books/{$book->id}", ['title' => 'New Title', 'total_copies' => 5])
        ->assertOk()
        ->assertJsonPath('data.available_copies', 5);

    $this->deleteJson("/api/books/{$book->id}")->assertNoContent();

    $this->assertDatabaseMissing('books', ['id' => $book->id]);
});

it('keeps a category that still contains books', function () {
    $book = Book::factory()->create();

    $this->deleteJson("/api/categories/{$book->category_id}")->assertStatus(409);

    $this->assertModelExists($book->category);
});
