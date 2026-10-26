<?php

test('the home page redirects to the login page', function () {
    $this->get('/')
        ->assertStatus(302)
        ->assertRedirect(route('login'));
});
