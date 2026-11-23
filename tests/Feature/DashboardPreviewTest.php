<?php

it('renders the dashboard preview', function () {
    $this->get('/dashboard')->assertOk()->assertSee('Total balance');
});
