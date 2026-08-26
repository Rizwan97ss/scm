<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addWeek();
        $end = (clone $start)->addDays(2);

        return [
            'user_id' => User::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $start->diffInDays($end) + 1,
            'reason' => fake()->sentence(),
            'status' => LeaveStatus::Pending,
        ];
    }
}
