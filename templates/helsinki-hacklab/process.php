<?php

// Start by fetching the doc
$curl = curl_init();
if ($curl === false) {
    die("Curl initialization failed\n");
}
if (curl_setopt_array($curl, [
    CURLOPT_COOKIEFILE => __DIR__.'/../../cookies.txt',
    CURLOPT_HTTPHEADER => [
        "X-DokuWiki-Do: export_xhtmlbody"
    ],
    CURLOPT_FAILONERROR => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_URL => $url,
]) === false) {
    die("Setting curl options failed\n");
}

// Load HTML
$html = curl_exec($curl);
if ($html === false) {
    die("Unable to load document from Wiki\n");
}

$doc = new DOMDocument();
$doc->loadHTML('<?xml encoding="UTF-8">'.$html);

// Strip unnecessary divs to mess up list compactness inferring in
// Pandoc

$xpath = new DOMXpath($doc);

$ugly_divs = $xpath->query("//div[@class='li']");
foreach ($ugly_divs as $ugly_div) {
    // Find out how much stuff there is
    $is_long = strlen($ugly_div->textContent) > 60;
    if ($is_long) {
        // Have longer spacing
        continue;
    }
    // Move content from the divs to the parent
    $par = $ugly_div->parentNode;
    while (true) {
        $child = $ugly_div->firstChild;
        if ($child === null) break;
        $par->insertBefore($child, $ugly_div);
    }
    // And remove the former parent element
    $par->removeChild($ugly_div);
}

// Let's mangle with Pandoc
$fds = [
    0 => ['pipe', 'r'],
    1 => STDOUT,
    2 => STDERR,
];

// To find template logos et cetera, resolve paths first, then change
// directory.

// File must exist to resolve unlike in Unix. So, touching.
$outfile = "/tmp/pk.pdf";
touch($outfile);
$esc_outfile = escapeshellarg(realpath($outfile));

if (!chdir(__DIR__)) {
    die("Unable to chdir to template directory\n");
}

$res = proc_open("pandoc -f html --shift-heading-level-by=-1 -H header.tex -B before.tex --metadata-file=metadata.yaml -o $esc_outfile", $fds, $pipes);
if ($res === false) {
    die("Unable to run pandoc\n");
}

if (fwrite($pipes[0], $doc->saveHTML()) === false) {
    die("Pandoc did not consume input\n");
}
if (proc_close($res) !== 0) {
    die("Pandoc failed\n");
}
