<?php

return [
    /*
     * Days ahead a customer may book a venue that hasn't set its own window. 60 matches how
     * far the app could already see availability, so introducing the window changed nothing
     * for a regular player; a venue can set a shorter one in /control.
     */
    'booking_window_days' => (int) env('VENUE_BOOKING_WINDOW_DAYS', 60),
];
