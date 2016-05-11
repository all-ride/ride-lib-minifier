<?php

namespace ride\library\minifier;

use ride\library\image\ImageUrlGenerator;
use ride\library\minifier\css\CSSMin;
use ride\library\minifier\exception\MinifierException;
use ride\library\system\file\File;

use \Exception;

/**
 * Optimize an array of css files into one file without comments and unnecessairy whitespaces
 */
class CssMinifier extends AbstractMinifier {

    /**
     * File extension (or type) of this optimizer
     * @var string
     */
    const EXTENSION = 'css';

    /**
     * CSS source minifier
     * @var \ride\library\minifier\css\CSSMin
     */
    private $cssMinifier;

    /**
     * Instance of image URL generator
     * @var \ride\library\image\ImageUrlGenerator
     */
    private $imageUrlGenerator;

    /**
     * Base URL for included resources
     * @var string
     */
    private $baseUrl;

    /**
     * Sets the instance of the image URL generator
     * @param \ride\library\image\ImageUrlGenerator $imageUrlGenerator
     * @return null
     */
    public function setImageUrlGenerator(ImageUrlGenerator $imageUrlGenerator) {
        $this->imageUrlGenerator = $imageUrlGenerator;
    }

    /**
     * Sets the base URL of the system
     * @param string $baseUrl
     * @return null
     */
    public function setBaseUrl($baseUrl) {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Gets all the files used by the provided CSS files
     * @param array $fileNames Array with the file names of CSS files
     * @return array Array with the path of the file as key and the File object as value
     */
    protected function getFilesFromArray(array $fileNames) {
        $files = array();

        $fileSystem = $this->fileBrowser->getFileSystem();

        foreach ($fileNames as $fileName) {
            $file = $fileSystem->getFile($fileName);
            if (!$file->isAbsolute()) {
                $file = $this->lookupFile($fileName);
                if (!$file) {
                    continue;
                }
            }

            $styleFiles = $this->getFilesFromStyle($file);
            $files += $styleFiles;
        }

        return $files;
    }

    /**
     * Gets all the files needed for the provided CSS file. This will extract the imports from the CSS.
     * @param \ride\library\system\file\File $file CSS source file
     * @return array Array with the path of the file as key and the File object as value
     */
    private function getFilesFromStyle(File $file) {
        $source = $file->read();
        $source = preg_replace(CSSMin::REGEX_COMMENT, '', $source);

        $files = array();

        $parent = $file->getParent();

        $lines = explode("\n", $source);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (!preg_match(CSSMin::REGEX_IMPORT, $line)) {
                break;
            }

            $importFileName = $this->getFileNameFromImportLine($line);
            $importFileName = $this->fileBrowser->getRelativeFile($parent->getChild($importFileName), true);
            $importFile = $this->fileBrowser->getPublicFile($importFileName);
            if (!$importFile) {
                $importFile = $this->fileBrowser->getFile($importFileName);

                if (!$importFile) {
                    continue;
                }
            }

            $styleFiles = $this->getFilesFromStyle($importFile);
            $files = $files + $styleFiles;
        }

        $files[$file->getPath()] = $file;

        return $files;
    }

    /**
     * Extracts the file name of a CSS import statement
     * @param string $line Line of the import statement
     * @return string File name referenced in the import statement
     */
    private function getFileNameFromImportLine($line) {
        $line = str_replace(array('@import', ';'), '', $line);
        $line = trim($line);

        if (strpos($line, ' ') !== false) {
            list($fileToken, $mediaToken) = explode(' ', $line, 2);
        } else {
            $fileToken = $line;
        }

        if (preg_match('/^url/', $fileToken)) {
            $fileToken = substr($fileToken, 3);
        }

        return str_replace(array('(', '"', '\'', ')'), '', $fileToken);
    }

    /**
     * Optimizes the provided CSS source
     * @param string $source CSS source
     * @param \ride\library\system\file\File $file The file of the source
     * @return string optimized and minified CSS source
     */
    protected function minifySource($source, File $file) {
        $source = preg_replace(CSSMin::REGEX_IMPORT, '', $source);
        $source = $this->getCssMinifier()->minify($source, true);

        $fileBrowser = $this->fileBrowser;
        $parent = $file->getParent();
        $baseUrl = $this->baseUrl;
        $imageUrlGenerator = $this->imageUrlGenerator;

        $source = preg_replace_callback(
            '/url( )?\\(["\']?([^;\\\\"\')]*)(["\']?)\\)([^;\\)]*)/',
            function ($matches) use ($fileBrowser, $parent, $file, $baseUrl, $imageUrlGenerator) {
                // absolute URLs
                if (substr($matches[2], 0, 7) == 'http://' || substr($matches[2], 0, 8) == 'https://') {
                    return "url(" . $matches[2] . ')' . $matches[4];
                }

                // handle url anchors
                if (strpos($matches[2], '#') !== false) {
                    list($matches[2], $hash) = explode('#', $matches[2], 2);
                    $hash = '#' . $hash;
                } else {
                    $hash = '';
                }

                // get the file from filesystem
                try {
                    $source = $parent->getChild($matches[2]);
                    $source = $fileBrowser->getRelativeFile($source, true);
                } catch (Exception $e) {
                    $source = $fileBrowser->getFileSystem()->getFile($matches[2]);
                }

                $publicPath = $fileBrowser->getPublicPath() . '/';

                if (in_array($source->getExtension(), array('gif', 'png', 'jpg', 'jpeg'))) {
                    // handle images
                    if (!$imageUrlGenerator) {
                        throw new MinifierException('Could not minify the css: no image URL generator set, use setImageUrlGenerator first.');
                    }

                    $source = $imageUrlGenerator->generateUrl($source->getPath());
                } elseif (substr($source->getPath(), 0, strlen($publicPath)) == $publicPath) {
                    // handle other public resources
                    $source = $baseUrl . '/' . substr($source->getPath(), strlen($publicPath));
                } else {
                    $source = $baseUrl . '/' . $source;
                }

                return "url(" . $source . $hash . ")" . $matches[4];
            },
            $source
        );

        $source = "\n\n/* " . $file->getName() . " */\n\n" . $source;

        return $source;
    }

    /**
     * Gets a hash for the provided files
     * @param array $files Array of file objects of the files to minimize
     * @return string MD5 hash of the file names
     */
    protected function getMinifiedFileHash(array $files) {
        return md5($this->baseUrl . '-' . implode('-', array_values($files)));
    }

    /**
     * Gets the CSS minifier
     * @return \ride\library\minifier\css\CSSMin
     */
    private function getCssMinifier() {
        if (!$this->cssMinifier) {
            $this->cssMinifier = new CSSMin();
        }

        return $this->cssMinifier;
    }

}
