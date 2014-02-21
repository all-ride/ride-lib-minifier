<?php

namespace ride\library\minifier\css;

/**
 * CSS source minifier
 */
class CSSMin {

    /**
     * Regular expression to match CSS comments
     * @var string
     */
    const REGEX_COMMENT = '#/\*.*?\*/#s';

    /**
     * Regular expression to match a CSS import
     * @var string
     */
    const REGEX_IMPORT = '/(@import([^;]*);)/';

    /**
     * Regular expression to match white spaces
     * @var string
     */
    const REGEX_WHITESPACES = '/\s*([{}|:;,])\s+/';

    /**
     * Regular expresion to match trailing whitespaces at the beginning
     * @var string
     */
    const REGEX_WHITESPACES_TRAILING_AT_BEGINNNING = '/\s*([{}|:;,])\s+/';

    /**
     * Minify the provided CSS source
     * @param string $source CSS source to minify
     * @param boolean $removeImports True to remove the import statements, false otherwise
     * @return string Minified CSS source
     */
    public function minify($source, $removeImports = false) {
        $source = preg_replace(self::REGEX_COMMENT, '', $source);
        $source = preg_replace(self::REGEX_WHITESPACES, '$1', $source);
        $source = preg_replace(self::REGEX_WHITESPACES_TRAILING_AT_BEGINNNING, '$1', $source);

        if ($removeImports) {
            $source = preg_replace(self::REGEX_IMPORT, '', $source);
        }

        $source = str_replace("\n", '', $source);

        return $source;
    }

    /**
     * Static access to the CSS minifier
     * @param string $source CSS source to minify
     * @param boolean $removeImports True to remove the import statements, false otherwise
     * @return string Minified CSS source
     */
    public static function min($source, $removeImports = false) {
        $cssMinifier = new CSSMin();

        return $cssMinifier->minify($source, $removeImports);
    }

}