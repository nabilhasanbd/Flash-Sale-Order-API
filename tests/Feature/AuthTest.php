<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'token',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ]);

        $this->assertEquals(201, $response->getStatusCode());

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'customer',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => 0.00,
        ]);
    }

    public function test_registration_requires_all_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_minimum_eight_characters_password(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
        ]);

        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_successfully(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $payload = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'token',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'customer',
                ],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $payload = [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(401)
            ->assertExactJson([
                'success' => false,
                'message' => 'Invalid credentials.',
            ]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertExactJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);

        $this->assertCount(0, $user->tokens);
    }

    public function test_guest_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User profile retrieved successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'customer',
                ],
            ]);
    }

    public function test_guest_cannot_retrieve_profile(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }
}
