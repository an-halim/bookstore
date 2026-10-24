<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoanResource;
use App\Models\Book;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $loans = Loan::query()
            ->whereNull('returned_at')
            ->with('book.category')
            ->orderByDesc('borrowed_at')
            ->orderByDesc('id');

        if (! $request->user()->isAdmin()) {
            $loans->whereBelongsTo($request->user());
        }

        return LoanResource::collection($loans->paginate(15));
    }

    public function store(Request $request): LoanResource
    {
        $data = $request->validate([
            'book_id' => ['required', 'integer', Rule::exists('books', 'id')->withoutTrashed()],
        ]);

        $user = $request->user();

        $loan = DB::transaction(function () use ($data, $user): Loan {
            $reserved = Book::query()
                ->whereKey($data['book_id'])
                ->where('available_copies', '>', 0)
                ->decrement('available_copies');

            abort_if($reserved === 0, 409, 'Book is not available.');

            return Loan::create([
                'book_id' => $data['book_id'],
                'user_id' => $user->id,
                'borrower_name' => $user->name,
                'borrower_email' => $user->email,
                'borrowed_at' => now(),
            ]);
        });

        return new LoanResource($loan->load('book.category'));
    }

    public function show(Loan $loan): LoanResource
    {
        Gate::authorize('access-loan', $loan);

        return new LoanResource($loan->load('book.category'));
    }

    public function return(Loan $loan): LoanResource
    {
        Gate::authorize('access-loan', $loan);

        $loan = DB::transaction(function () use ($loan): Loan {
            $returned = Loan::query()
                ->whereKey($loan->id)
                ->whereNull('returned_at')
                ->update(['returned_at' => now()]);

            abort_if($returned === 0, 409, 'Book has already been returned.');

            Book::query()->whereKey($loan->book_id)->increment('available_copies');

            return $loan->refresh();
        });

        return new LoanResource($loan->load('book.category'));
    }
}
