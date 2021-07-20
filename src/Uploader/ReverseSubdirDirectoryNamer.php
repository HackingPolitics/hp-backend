<?php

declare(strict_types=1);

namespace App\Uploader;

use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\ConfigurableInterface;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;

/**
 * Uses the _last_ N characters of the filename to create a subdirectory name.
 * This is used for better interaction with the SmartUniqueNamer which appends
 * the uniqid instead of prepending. This way we always have hex characters
 * in the directory name which should be evenly distributed and still have
 * the human readable original filename at the front.
 */
class ReverseSubdirDirectoryNamer implements DirectoryNamerInterface, ConfigurableInterface
{
    protected $charsPerDir = 2;
    protected $dirs = 1;

    /**
     * @param array $options Options for this namer. The following options are accepted:
     *                       - chars_per_dir: how many chars use for each dir.
     *                       - dirs: how many dirs create
     */
    public function configure(array $options): void
    {
        $options = \array_merge(['chars_per_dir' => $this->charsPerDir, 'dirs' => $this->dirs], $options);

        $this->charsPerDir = $options['chars_per_dir'];
        $this->dirs = $options['dirs'];
    }

    public function directoryName($object, PropertyMapping $mapping): string
    {
        $fileName = \pathinfo($mapping->getFileName($object), PATHINFO_FILENAME);
        $minLength = $this->charsPerDir * $this->dirs;
        if (strlen($fileName) < $minLength) {
            $fileName = str_pad($fileName, $minLength, 'z');
        }

        $parts = [];
        for ($i = 0, $start = -$this->charsPerDir; $i < $this->dirs; $i++, $start -= $this->charsPerDir) {
            $parts[] = \substr($fileName, $start, $this->charsPerDir);
        }

        return \implode('/', $parts);
    }
}
