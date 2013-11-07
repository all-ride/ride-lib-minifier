<?php

namespace pallo\library\minifier;

/**
 * Minifier interface for a combination of resources
 */
interface Minifier {

    /**
     * Minifies an array of resources into 1 resource
     * @param array $resources Array of resources which need to be minified
     * into 1 resource
     * @return pallo\library\system\file\File File of the minified resources
     */
    public function minify(array $resources);

}