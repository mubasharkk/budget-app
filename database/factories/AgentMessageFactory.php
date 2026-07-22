<?php

namespace Database\Factories;

use App\Models\AgentMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentMessage>
 */
class AgentMessageFactory extends Factory
{
    /**
     * @var class-string<AgentMessage>
     */
    protected $model = AgentMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => 'user',
            'content' => fake()->sentence(),
            'data' => null,
            'mentions' => null,
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (): array => ['role' => 'assistant']);
    }
}
