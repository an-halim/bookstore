<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'borrower_name' => fake()->name(),
            'borrower_email' => fake()->safeEmail(),
            'borrowed_at' => now(),
            'returned_at' => null,
        ];
    }

    public function forBorrower(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'borrower_name' => $user->name,
            'borrower_email' => $user->email,
        ]);
    }
}
