<?php

require_once __DIR__.'/SearchController.php';

$sc = new SearchController();
$bookmarks = $sc->db->query("SELECT
            ID as ID_R
            FROM LIST_MOBILE_USER_BOOKMARKS WHERE IS_DELETED = 0
            ORDER BY LAST_UPDATE ASC ROWS 1
        ");

$sc->bookmarkInnerSearch($bookmarks[0]["ID_R"], null);