<?php

it('does not expose public employee registration', function () {
    $this->get('/register')->assertNotFound();
});
