<?php

namespace Database\Factories;

use App\Enums\VendorApplicationStatus;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorApplication>
 */
class VendorApplicationFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (VendorApplication $application): void {
            if ($application->status === VendorApplicationStatus::Pending && $application->pending_for_user_id !== $application->user_id) {
                $application->forceFill(['pending_for_user_id' => $application->user_id])->save();
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store_name' => fake()->company(),
            'note' => fake()->optional()->sentence(),
            'status' => VendorApplicationStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorApplicationStatus::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorApplicationStatus::Approved,
            'pending_for_user_id' => null,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorApplicationStatus::Rejected,
            'pending_for_user_id' => null,
            'rejection_reason' => 'Incomplete details',
            'reviewed_at' => now(),
        ]);
    }
}
