<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    public function definition(): array
    {
        $copies = fake()->numberBetween(1, 10);

        return [
            'title' => fake()->unique()->sentence(3),
            'author' => fake()->name(),
            'isbn' => '978-'.fake()->unique()->numerify('#-#####-###-#'),
            'category' => fake()->randomElement(['Fiction', 'Science', 'History', 'Mathematics', 'Reference']),
            'total_copies' => $copies,
            'available_copies' => $copies,
            'is_active' => true,
        ];
    }
}
