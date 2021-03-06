<?php

namespace Arsh\Core\Table\Files;

use Arsh\Core\Table\TableSegment;
use Arsh\Core\Folder;
use Arsh\Core\File;
use Arsh\Core\Web;
use Arsh\Core\ENV;

final class Doc implements TableSegment {
    private $class;
    private $id_table = NULL;
    private $filekey;
    private $folder;
    private $urls = NULL; // if no files in uploads/

    function __construct (string $class, int $id_table = NULL, string $filekey) {
        $this->class    = $class;
        $this->id_table = $id_table;
        $this->filekey  = $filekey;
        $this->folder   = (Folder::encode($class) .'/'. $id_table .'/'. $filekey);

        $files = File::tree(ENV::uploads(true). $this->folder, NULL, false, true);

        if ($files) {
            $site = Web::site();

            foreach ((($this->class)::TRANSLATOR)::LANGUAGES as $language) {
                if (!isset($files[$language])) {
                    $first_lang = array_key_first($files);

                    if (Folder::copy(ENV::uploads(true). $this->folder .'/'. $first_lang, ENV::uploads(true). $this->folder .'/'. $language)) {
                        $files[$language] = $files[$first_lang];
                    }
                }

                if (!empty($files[$language])) {
                    $this->urls[$language] = ($site .ENV::uploads(true). $this->folder .'/'. $language .'/'. array_values($files[$language])[0]);
                }
            }
        }
    }

    function class (): string {
        return $this->class;
    }

    function id (): ?int {
        return $this->id_table;
    }

    function key (): string {
        return $this->filekey;
    }

    function isTranslated (): bool {
        return true;
    }

    function url (string $lang = NULL): ?string {
        return $this->urls[($lang ?: (($this->class)::TRANSLATOR)::get())];
    }

    function __call (string $method, array $args) {
        return $this->{$method}; // class, id_table, filekey, folder
    }

    function rename (string $name, string $language = NULL): void {
        $language = ($language ?: (($this->class)::TRANSLATOR)::default());

        $file_ext = ('.'. File::extension(File::rFirst(ENV::uploads(true). $this->folder .'/'. $language)));

        foreach (File::rFolder(ENV::uploads(true). $this->folder .'/'. $language) as $file) {
            rename($file, dirname($file) .'/'. $name . $file_ext);
        }
    }

    function update (array $data, string $language = NULL): void {
        $language = ($language ?: (($this->class)::TRANSLATOR)::default());

        $dirname = ENV::uploads(true).$this->folder.'/'.$language;

        Folder::remove($dirname);
        mkdir($dirname, 0755, true);

        if (isset($data['content'])) {
            file_put_contents(
                ENV::uploads(true).$this->folder.'/'.$language.'/'.$data['name'],
                $data['content'],
                LOCK_EX
            );
        }
        else {
            copy($data['tmp_name'], ENV::uploads(true).$this->folder.'/'.$language.'/'.$data['name']);
        }

        $this->urls[$language] = Web::site().ENV::uploads(true).$this->folder.'/'.$language.'/'.$data['name'];
    }

    function delete (string $language = NULL): bool {
        Folder::remove(ENV::uploads(true). $this->folder .'/'. ($language ?? ''));

        Folder::removeEmpty(ENV::uploads(true). dirname($this->folder));

        if (!$language) {
            $this->urls = NULL;
        }

        return true;
    }
}
