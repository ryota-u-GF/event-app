<?php

test('the home page redirects to the event list', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('events.index'));
});
