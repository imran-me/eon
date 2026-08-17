<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
/* Firestore-shaped doc store for the browser brain:  GET ?doc=col/id   |   PUT ?doc=col/id  {data, merge?} */
Http::run(function () {
    Http::auth();
    $doc = (string) Http::q('doc', '');
    if ($doc === '' || !preg_match('#^[a-z0-9_\-]+/[a-z0-9_\-]+$#i', $doc)) Http::fail(400, 'doc must look like collection/id');
    if (Http::method() === 'GET') Http::json(['ok' => true, 'doc' => $doc, 'data' => Memory::docGet($doc)]);
    if (in_array(Http::method(), ['PUT', 'POST'], true)) {
        $b = Http::body();
        if (!is_array($b['data'] ?? null)) Http::fail(400, 'data object required');
        Http::json(['ok' => true, 'doc' => $doc, 'data' => Memory::docPut($doc, $b['data'], (bool) ($b['merge'] ?? false))]);
    }
    Http::fail(405, 'GET or PUT');
});
