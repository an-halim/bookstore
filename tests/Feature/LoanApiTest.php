<?php

use App\Models\Book;
use App\Models\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lends an available book and lists active loans', function () {
    $book = Book::factory()->create(['total_copies' => 1, 'available_copies' => 1]);

    $response = $this->postJson('/api/loans', [
        'book_id' => $book->id,
        'borrower_name' => 'Rina',
        'borrower_email' => 'rina@example.com',
    ])->assertCreated()
        ->assertJsonPath('data.book.id', $book->id)
        ->assertJsonMissingPath('data.borrower_name')
        ->assertJsonMissingPath('data.borrower_email');

    $this->getJson('/api/loans')
        ->assertOk()
        ->assertJsonPath('data.0.book.id', $book->id)
        ->assertJsonMissingPath('data.0.borrower_name');

    $this->getJson('/api/loans/'.$response->json('data.id'))
        ->assertOk()
        ->assertJsonMissingPath('data.borrower_name')
        ->assertJsonMissingPath('data.borrower_email');

    $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 0]);
    $this->assertDatabaseHas('loans', ['book_id' => $book->id, 'borrower_email' => 'rina@example.com']);
});

it('returns 409 without making a loan when no copy is available', function () {
    $book = Book::factory()->create(['total_copies' => 1, 'available_copies' => 0]);

    $this->postJson('/api/loans', [
        'book_id' => $book->id,
        'borrower_name' => 'Rina',
        'borrower_email' => 'rina@example.com',
    ])->assertStatus(409);

    $this->assertDatabaseCount('loans', 0);
    $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 0]);
});

it('rejects a loan with invalid borrower details', function () {
    $book = Book::factory()->create();

    $this->postJson('/api/loans', [
        'book_id' => $book->id,
        'borrower_name' => '',
        'borrower_email' => 'invalid',
    ])->assertUnprocessable()->assertJsonValidationErrors(['borrower_name', 'borrower_email']);

    $this->assertDatabaseCount('loans', 0);
    $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 3]);
});

it('returns a book once and removes it from active loans', function () {
    $book = Book::factory()->create(['total_copies' => 1, 'available_copies' => 0]);
    $loan = Loan::factory()->for($book)->create();

    $this->postJson("/api/loans/{$loan->id}/return")
        ->assertOk()
        ->assertJsonPath('data.book.available_copies', 1);

    $this->getJson('/api/loans')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson("/api/loans/{$loan->id}/return")->assertStatus(409);

    $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 1]);
    $this->assertDatabaseHas('loans', ['id' => $loan->id, 'returned_at' => $loan->fresh()->returned_at]);
});

it('does not shrink inventory below borrowed copies', function () {
    $book = Book::factory()->create(['total_copies' => 2, 'available_copies' => 1]);
    Loan::factory()->for($book)->create();

    $this->patchJson("/api/books/{$book->id}", ['total_copies' => 1])
        ->assertOk()
        ->assertJsonPath('data.available_copies', 0);

    $this->patchJson("/api/books/{$book->id}", ['total_copies' => 2])
        ->assertOk()
        ->assertJsonPath('data.available_copies', 1);

    Loan::factory()->for($book)->create();
    $book->update(['available_copies' => 0]);

    $this->patchJson("/api/books/{$book->id}", ['total_copies' => 1])->assertStatus(409);

    $this->assertDatabaseHas('books', ['id' => $book->id, 'total_copies' => 2, 'available_copies' => 0]);
});

it('rejects archiving a book with an active loan', function () {
    $book = Book::factory()->create();
    Loan::factory()->for($book)->create();

    $this->deleteJson("/api/books/{$book->id}")->assertStatus(409);

    $this->assertModelExists($book);
});

it('archives a returned book while preserving its loan history', function () {
    $book = Book::factory()->create();
    $loan = Loan::factory()->for($book)->create(['returned_at' => now()]);

    $this->deleteJson("/api/books/{$book->id}")->assertNoContent();
    $this->getJson("/api/books/{$book->id}")->assertNotFound();
    $this->getJson('/api/books')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/loans/{$loan->id}")
        ->assertOk()
        ->assertJsonPath('data.book.id', $book->id);

    $this->assertSoftDeleted($book);
    $this->assertModelExists($loan);
});

it('rejects borrowing an archived book', function () {
    $book = Book::factory()->create();
    $this->deleteJson("/api/books/{$book->id}")->assertNoContent();

    $this->postJson('/api/loans', [
        'book_id' => $book->id,
        'borrower_name' => 'Rina',
        'borrower_email' => 'rina@example.com',
    ])->assertUnprocessable()->assertJsonValidationErrors('book_id');

    $this->assertDatabaseCount('loans', 0);
});
