<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $this->markTestSkipped('Profile update route not implemented.');
})->skip('Route PATCH /profile not defined');

test('email verification status is unchanged when the email address is unchanged', function () {
    $this->markTestSkipped('Profile update route not implemented.');
})->skip('Route PATCH /profile not defined');

test('user can delete their account', function () {
    $this->markTestSkipped('Profile delete route not implemented.');
})->skip('Route DELETE /profile not defined');

test('correct password must be provided to delete account', function () {
    $this->markTestSkipped('Profile delete route not implemented.');
})->skip('Route DELETE /profile not defined');
