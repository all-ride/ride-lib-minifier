<?php

namespace pallo\library\minifier;

use pallo\library\system\file\browser\FileBrowser;
use pallo\library\system\file\File;

/**
 * Abstract implementation of the minifier interface
 */
abstract class AbstractMinifier implements Minifier {

    /**
     * Instance of the file browser
     * @var pallo\core\environment\filebrowser\FileBrowser
     */
    protected $fileBrowser;

    /**
     * Path where the files will be cached
     * @var pallo\library\system\file\File
     */
    private $cachePath;

    /**
     * Flag to see if lazy mode is on
     * @var boolean
     */
    private $lazy;

    /**
     * Constructs a new optimizer
     * @param pallo\library\system\file\browser\FileBrowser $fileBrowser
     * @param string $cachePath Path in the public directory
     * @return null
     */
    public function __construct(FileBrowser $fileBrowser, $cachePath = null) {
        if (!$cachePath) {
            $cachePath = 'cache/' . $this->getExtension();
        }

        $this->fileBrowser = $fileBrowser;
        $this->cachePath = $fileBrowser->getPublicDirectory()->getChild($cachePath);
        $this->lazy = false;
    }

    /**
     * Gets the extension of this optimizer
     * @return string
     */
    protected function getExtension() {
        return static::EXTENSION;
    }

    /**
     * Sets the lazy flag
     * @param string $lazy
     * @return null
     */
    public function setLazy($lazy) {
        $this->lazy = $lazy;
    }

    /**
     * Minifies an array of resources into 1 resource
     * @param array $resources Array of resources which need to be minified
     * into 1 resource
     * @return pallo\library\system\file\File File of the minified resources
     */
    public function minify(array $resources) {
        $minifiedFile = $this->getMinifiedFile($resources);

        if (!($this->lazy && $minifiedFile->exists())) {
            $files = $this->getFilesFromArray($resources);

            if ($this->isGenerateNecessairy($minifiedFile, $files)) {
                $this->generateMinifiedFile($minifiedFile, $files);
            }
        }

        return $minifiedFile;
    }

    /**
     * Gets the file objects for the file names
     * @param array $fileNames Array with the file names
     * @return array Array with the file name as key and the File objact as value
     */
    protected function getFilesFromArray(array $fileNames) {
        $files = array();

        foreach ($fileNames as $fileName) {
            if ($fileName instanceof File && $fileName->isAbsolute()) {
                $file = $fileName;
            } else {
                $file = $this->lookupFile($fileName);
                if (!$file) {
                    continue;
                }
            }

            $files[$file->getAbsolutePath()] = $file;
        }

        return $files;
    }

    /**
     * Looks up a file from public to application
     * @param string $fileName
     * @return pallo\library\filesystem\File|null
     */
    protected function lookupFile($fileName) {
        $file = $this->fileBrowser->getPublicFile($fileName);
        if ($file) {
            return $file;
        }

        return $this->fileBrowser->getFile($fileName);
    }

    /**
     * Gets the minized file object for the provided files
     * @param array $files Array of file objects of the files to be minified
     * @return pallo\library\filesystem\File File object of the minified file
     */
    protected function getMinifiedFile(array $files) {
        $fileName = $this->getMinifiedFileHash($files);
        $fileName .= '.' . $this->getExtension();

        return $this->cachePath->getChild($fileName);
    }

    /**
     * Gets a hash for the provided files
     * @param array $files Array of file objects of the files to minimize
     * @return string MD5 hash of the file names
     */
    protected function getMinifiedFileHash(array $files) {
        return md5(implode('-', array_values($files)));
    }

    /**
     * Gets whether a new generation of the minified file is necessairy
     * @param pallo\library\filesystem\File $minifiedFile The file of the minified source
     * @param array $files Array with File objects of the files to minimize
     * @return boolean True if a new generation is necessairy, false otherwise
     */
    private function isGenerateNecessairy(File $minifiedFile, array $files) {
        if (!$minifiedFile->exists()) {
            $parent = $minifiedFile->getParent();
            if (!$parent->exists()) {
                $parent->create();
            }

            return true;
        }

        $cacheTime = $minifiedFile->getModificationTime();
        foreach ($files as $file) {
            if ($file->getModificationTime() >= $cacheTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates the minified source file
     * @param pallo\library\filesystem\File $minifiedFile The file of the minimize source
     * @param array $files Array with File objects of the files to minimize
     * @return null
     */
    protected function generateMinifiedFile(File $minifiedFile, array $files) {
        $output = '';

        foreach ($files as $file) {
            $source = $file->read();
            $output .= $this->minifySource($source, $file);
        }

        $minifiedFile->write($output);
    }

    /**
     * Minimizes the provided source
     * @param string $source The source to minimize
     * @param pallo\library\filesystem\File $file The file of the source
     * @return string Minified source
     */
    abstract protected function minifySource($source, File $file);

}