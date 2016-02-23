#!/usr/bin/env php
<?php

$template = file_get_contents('./assets/template.fo');
$template = str_replace('%GENRE-STRING%', 'Electronic Theses and Dissertations', $template);
$template = str_replace('%DEPT-STRING%', 'Grad School', $template);
$template = str_replace('%DATE-STRING%', '2004', $template);
$template = str_replace('%TITLE-STRING%', 'An ETD by any other name', $template);
$template = str_replace('%AUTHOR-STRING%', 'Bryan Brown', $template);

$modtemplate = fopen("./test/mod-template.fo", "w") or die("Unable to open file!");
fwrite($modtemplate, $template);
fclose($modtemplate);

shell_exec("assets/fop-1.1/fop ./test/mod-template.fo ./test/coverpage.pdf > /dev/null 2>&1");
shell_exec("gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress -sOutputFile=final.pdf test/coverpage.pdf test/test.pdf");

?>
