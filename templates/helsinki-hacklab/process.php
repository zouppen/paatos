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

// Table right-align hack, force Dokuwiki CSS to inline style before
// Pandoc. NB! Not a fully specs-conforming class parser!
$right_aligns = $xpath->query("//*[contains(concat(' ', @class, ' '), ' rightalign ')]");
foreach ($right_aligns as $el) {
    $el->setAttribute('align', 'right');
}

// Footnote inliner
$footnotes = $xpath->query("//*[@class='fn']");
foreach ($footnotes as $footnote) {
    // Get link and contents
    $link = @$xpath->query(".//@href", $footnote)[0]->value;
    $payload = @$xpath->query(".//*[@class='content']", $footnote)[0];

    if ($link === null || $payload === null) {
        // Maybe non-fatal, but not sure. Printing.
        print("Footnote found but we aren't able to parse it\n");
        continue;
    }
    // Remove hash
    $id = substr($link, 1);

    // Reformat the tag as span
    $span = $doc->createElement('span');
    $span->setAttribute('class', 'fn');
    while (true) {
        $child = $payload->firstChild;
        if ($child === null) break;
        $span->append($child);
    }

    // Replace the reference
    $home = $doc->getElementById($id);
    if ($home === null) {
        // Maybe non-fatal, but not sure. Printing.
        print("Footnote found ($link) but not the element it was referred from\n");
        continue;
    }
    $sup = $home->parentNode;
    if ($sup->nodeName !== 'sup') {
        // Maybe non-fatal, but not sure. Printing.
        print("Footnote reference not in superscript.\n");
        continue;
    }
    $sup->replaceWith($span);

    // Clear footnote (TODO maybe remove div as well)
    $footnote->textContent = '';
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
touch($outfile);
$esc_outfile = escapeshellarg(realpath($outfile));

if (!chdir(__DIR__)) {
    die("Unable to chdir to template directory\n");
}

$res = proc_open("pandoc -f html --shift-heading-level-by=-1 -H header.tex -B before.tex --metadata-file=metadata.yaml -F filter -o $esc_outfile", $fds, $pipes);
if ($res === false) {
    die("Unable to run pandoc\n");
}

if (fwrite($pipes[0], $doc->saveHTML()) === false) {
    die("Pandoc did not consume input\n");
}
if (proc_close($res) !== 0) {
    die("Pandoc failed\n");
}
