<?php

namespace pallo\library\minifier;

use pallo\library\minifier\js\JSMin;
use pallo\library\system\file\browser\FileBrowser;
use pallo\library\system\file\File;

/**
 * Minifier for js files
 */
class JsMinifier extends AbstractMinifier {

    /**
     * File extension (or type) of this optimizer
     * @var string
     */
    const EXTENSION = 'js';

    /**
     * Minifies the provided JS source
     * @param string $source JS source
     * @param pallo\library\filesystem\File $file The file of the source
     * @return string Minified JS source
     */
    protected function minifySource($source, File $file) {
        $minified = JSMin::minify($source);
        $minified = str_replace('"+++', '"+ ++', $minified);

        return $minified;
    }

}