<?php

return [
    'cr_nav_poll_interval_ms' => max(1000, (int) env('CR_NAV_POLL_INTERVAL_SECONDS', 15) * 1000),
];
