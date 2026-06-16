<?php

it('mengarahkan root ke dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});
