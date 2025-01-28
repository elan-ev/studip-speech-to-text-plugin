<?php

namespace SpeechToTextPlugin\Files;

use FileType;
use Folder;
use FolderType;
use Icon;
use SpeechToTextPlugin\Models\Job;
use StandardFolder;

class PublicFolder extends StandardFolder
{
    protected $folder;

    public function __construct($folder = null)
    {
        $this->folder = $folder instanceof Folder ? $folder : Folder::build($folder);
        $this->folder['folder_type'] = static::class;
    }

    public function __get($attribute)
    {
        return $this->folder[$attribute];
    }

    public static function availableInRange($rangeIdOrObject, $userId)
    {
        return false;
    }

    public static function createTopFolder(Job $job): PublicFolder
    {
        return new PublicFolder(Folder::createTopFolder($job->id, $job::class, PublicFolder::class));
    }

    public function createSubfolder(FolderType $folderdata)
    {
        return null;
    }

    public function delete()
    {
        return $this->folder->delete();
    }

    public function deleteFile($fileRefId)
    {
        $fileRefs = $this->folder->file_refs;

        if (is_array($fileRefs)) {
            foreach ($fileRefs as $fileRef) {
                if ($fileRef->id === $fileRefId) {
                    return $fileRef->delete();
                }
            }
        }

        return false;
    }

    public function deleteSubfolder($subfolderId)
    {
        return false;
    }

    public static function findOrCreateTopFolder(Job $job): PublicFolder
    {
        if (!($folder = self::findTopFolder($job))) {
            $folder = self::createTopFolder($job);
        }

        return $folder;
    }

    public static function findTopFolder(Job $job): ?PublicFolder
    {
        $folder = Folder::findOneBySql('range_id = ? AND range_type = ?', [$job->id, $job::class]);
        if ($folder) {
            return new PublicFolder($folder);
        }

        return null;
    }

    public function getDescriptionTemplate()
    {
        return '';
    }

    public function getEditTemplate()
    {
        return '';
    }

    public function getIcon($role = Icon::DEFAULT_ROLE)
    {
        return Icon::create('folder-public-full', $role);
    }

    public function getId()
    {
        return $this->folder->id;
    }

    public function getParent()
    {
        return null;
    }

    public function getSubfolders()
    {
        return [];
    }

    public static function getTypeName()
    {
        return _('Ein Ordner für Audiodateien des SpeechToTextPlugin');
    }

    public function isEditable($userId)
    {
        return false;
    }

    public function isFileDownloadable($fileRefId, $userId)
    {
        return true;
    }

    public function isFileEditable($fileRefId, $userId)
    {
        return false;
    }

    public function isFileWritable($fileRefId, $userId)
    {
        return false;
    }

    public function isReadable($userId)
    {
        return true;
    }

    public function isSubfolderAllowed($userId)
    {
        return false;
    }

    public function isVisible($userId)
    {
        return true;
    }

    public function isWritable($userId)
    {
        return true;
    }

    public function setDataFromEditTemplate($request)
    {
        return $this;
    }

    public function store()
    {
        return $this->folder->store();
    }
}
