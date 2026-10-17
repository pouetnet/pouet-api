<?php

// PER-CS 3.0 for the site's PHP, checked by
// .github/workflows/php-cs-fixer.yaml. Fix with `php-cs-fixer fix`. Skips
// the gitignored local files

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->ignoreVCSIgnored(true);

return (new PhpCsFixer\Config())
    ->setRules(["@PER-CS3.0" => true])
    ->setFinder($finder);
