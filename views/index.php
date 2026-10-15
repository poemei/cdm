<?php
if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}

http_response_code(403);
echo '<main>';
echo 'DSD diagnostics are available only through the administrative interface.';
echo '</main>';

if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
