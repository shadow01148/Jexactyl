<?php

namespace Pterodactyl\Tests\Integration\Api\Client;

use Mockery;
use Pterodactyl\Models\User;
use Illuminate\Http\Response;
use Illuminate\Auth\AuthManager;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class AccountControllerTest extends IntegrationTestCase
{
    /**
     * Clean up after running tests.
     */
    protected function tearDown(): void
    {
        User::query()->forceDelete();

        parent::tearDown();
    }

    /**
     * Test that the user's account details are returned from the account endpoint.
     */
    public function testAccountDetailsAreReturned()
    {
        /** @var \Pterodactyl\Models\User $user */
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->get('/api/client/account');

        $response->assertOk()->assertJson([
            'object' => 'user',
            'attributes' => [
                'id' => $user->id,
                'admin' => false,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->name_first,
                'last_name' => $user->name_last,
                'language' => $user->language,
            ],
        ]);
    }

    /**
     * Test that the user's email address can be updated via the API.
     */
    public function testEmailIsUpdated()
    {
        /** @var \Pterodactyl\Models\User $user */
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->putJson('/api/client/account/email', [
            'email' => 'hodor@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'hodor@example.com']);
    }

    /**
     * Tests that an email is not updated if the password provided in the reuqest is not
     * valid for the account.
     */
    public function testEmailIsNotUpdatedWhenPasswordIsInvalid()
    {
        /** @var \Pterodactyl\Models\User $user */
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->putJson('/api/client/account/email', [
            'email' => 'hodor@example.com',
            'password' => 'invalid',
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonPath('errors.0.code', 'InvalidPasswordProvidedException');
        $response->assertJsonPath('errors.0.detail', 'The password provided was invalid for this account.');
    }

    /**
     * Tests that an email is not updated if an invalid email address is passed through
     * in the request.
     */
    public function testEmailIsNotUpdatedWhenNotValid()
    {
        /** @var \Pterodactyl\Models\User $user */
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->putJson('/api/client/account/email', [
            'email' => '',
            'password' => 'password',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonPath('errors.0.code', 'required');
        $response->assertJsonPath('errors.0.detail', 'The email field is required.');

        $response = $this->actingAs($user)->putJson('/api/client/account/email', [
            'email' => 'invalid',
            'password' => 'password',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonPath('errors.0.code', 'email');
        $response->assertJsonPath('errors.0.detail', 'The email must be a valid email address.');
    }

    /**
     * Test that the password for an account can be successfully updated.
     */
    public function testPasswordIsUpdated()
    {
        /** @var \Pterodactyl\Models\User $user */
        $user = factory(User::class)->create();

        $mock = Mockery::mock(AuthManager::class);
        $mock->expects('logoutOtherDevices')->with('New_Password1');

        $this->app->instance(AuthManager::class, $mock);

        $response = $this->actingAs($user)->putJson('/api/client/account/password', [
            'current_password' => 'password',
            'password' => 'New_Password1',
            'password_confirmation' => 'New_Password1',
        ]);

        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    /**
     * Test that the password for an account is not updated if the current password is not
     * provided correctly.
     */
    public function testPasswordIsNotUpdatedIfCurrentPasswordIsInvalid()
    {
        /** @var \Pterodactyl\Models\User $user */
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->putJson('/api/client/account/password', [
            'current_password' => 'invalid',
            'password' => 'New_Password1',
            'password_confirmation' => 'New_Password1',
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonPath('errors.0.code', 'InvalidPasswordProvidedException');
        $response->assertJsonPath('errors.0.detail', 'The password provided was invalid for this account.');
    }

    /**
     * Test that a validation error is returned if the password passed in the request
     * does not have a confirmation, or the confirmation is not the same as the password.
     */
    public function testErrorIsReturnedIfPasswordIsNotConfirmed()
    {
        /** @var \Pterodactyl\Models\User $user */
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->putJson('/api/client/account/password', [
            'current_password' => 'password',
            'password' => 'New_Password1',
            'password_confirmation' => 'Invalid_New_Password',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonPath('errors.0.code', 'confirmed');
        $response->assertJsonPath('errors.0.detail', 'The password confirmation does not match.');
    }
}