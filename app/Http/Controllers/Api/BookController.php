<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BookResource::collection(Book::query()->with('category')->orderBy('title')->orderBy('id')->paginate(15));
    }

    public function store(Request $request): BookResource
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'max:20', 'unique:books,isbn'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);

        $data['available_copies'] = $data['total_copies'];

        return new BookResource(Book::create($data)->load('category'));
    }

    public function show(Book $book): BookResource
    {
        return new BookResource($book->load('category'));
    }

    public function update(Request $request, Book $book): BookResource
    {
        $data = $request->validate([
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'isbn' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('books', 'isbn')->ignore($book)],
            'total_copies' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        $book = DB::transaction(function () use ($book, $data): Book {
            $lockedBook = Book::query()->lockForUpdate()->findOrFail($book->id);

            if (isset($data['total_copies'])) {
                $borrowedCopies = $lockedBook->total_copies - $lockedBook->available_copies;
                abort_if($data['total_copies'] < $borrowedCopies, 409, 'Total copies cannot be less than borrowed copies.');
                $data['available_copies'] = $data['total_copies'] - $borrowedCopies;
            }

            $lockedBook->update($data);

            return $lockedBook;
        });

        return new BookResource($book->load('category'));
    }

    public function destroy(Book $book): Response
    {
        $book->delete();

        return response()->noContent();
    }
}
